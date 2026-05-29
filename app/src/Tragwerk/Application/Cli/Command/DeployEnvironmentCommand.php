<?php

declare(strict_types=1);

namespace Tragwerk\Application\Cli\Command;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Exception\NoKeyLoadedException;
use phpseclib3\Net\SFTP;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Tragwerk\Domain\Entity\BuildLog;
use Tragwerk\Domain\Enum\BuildLogType;
use Tragwerk\Domain\Repository\BuildLogRepository;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\BuildLogIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Infrastructure\Git\BareRepository;

use function assert;
use function basename;
use function copy;
use function escapeshellarg;
use function exec;
use function glob;
use function implode;
use function is_dir;
use function is_string;
use function mkdir;
use function rmdir;
use function rtrim;
use function sprintf;
use function strpos;
use function substr;
use function sys_get_temp_dir;
use function trim;
use function uniqid;
use function unlink;

#[AsCommand(name: 'project:deploy', description: 'Deploy a built environment to the target server')]
final class DeployEnvironmentCommand extends Command
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ServerRepository $serverRepository,
        private readonly CredentialRepository $credentialRepository,
        private readonly BuildLogRepository $buildLogRepository,
        private readonly BareRepository $bareRepository,
        private readonly string $projectDataPath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('project-id', InputArgument::REQUIRED, 'Project UUID')
            ->addArgument('branch', InputArgument::REQUIRED, 'Git branch name')
            ->addArgument('commit-sha', InputArgument::REQUIRED, 'Git commit SHA');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectId = $input->getArgument('project-id');
        $branch    = $input->getArgument('branch');
        $commitSha = $input->getArgument('commit-sha');

        assert(is_string($projectId));
        assert(is_string($branch));
        assert(is_string($commitSha));

        $id = ProjectIdentifier::fromString($projectId);

        try {
            $project = $this->projectRepository->getById($id);
        } catch (Throwable $e) {
            $this->log($id, $branch, '[Deploy] Project not found: ' . $e->getMessage());

            return Command::FAILURE;
        }

        try {
            $server = $this->serverRepository->getById($project->serverId);
        } catch (Throwable $e) {
            $this->log($id, $branch, '[Deploy] Server not found: ' . $e->getMessage());

            return Command::FAILURE;
        }

        if ($server->credentialId === null) {
            $this->log($id, $branch, '[Deploy] No credential assigned to server.');

            return Command::FAILURE;
        }

        try {
            $credential = $this->credentialRepository->getById($server->credentialId);
        } catch (Throwable $e) {
            $this->log($id, $branch, '[Deploy] Credential not found: ' . $e->getMessage());

            return Command::FAILURE;
        }

        if ($credential->privateKey === null) {
            $this->log($id, $branch, '[Deploy] Credential has no SSH key.');

            return Command::FAILURE;
        }

        try {
            $key = PublicKeyLoader::loadPrivateKey($credential->privateKey);
        } catch (NoKeyLoadedException $e) {
            $this->log($id, $branch, '[Deploy] Failed to load SSH key: ' . $e->getMessage());

            return Command::FAILURE;
        }

        // Phase 2 hook: if project has a registry assigned, branch here for registry-based deploy.
        // For now, always use Mode A: build on target server.

        $tempDir = sys_get_temp_dir() . '/tw-deploy-src-' . uniqid();
        mkdir($tempDir, 0755, true);

        $repoPath = $this->bareRepository->getPath($projectId);

        exec(
            'git -C ' . escapeshellarg($repoPath)
            . ' archive ' . escapeshellarg($commitSha)
            . ' | tar xf - -C ' . escapeshellarg($tempDir) . ' 2>&1',
            $archiveOut,
            $archiveExit,
        );

        if ($archiveExit !== 0) {
            $this->removeDirectory($tempDir);
            $this->log($id, $branch, '[Deploy] Failed to export source from git: ' . implode("\n", $archiveOut));

            return Command::FAILURE;
        }

        $buildDir = rtrim($this->projectDataPath, '/') . '/' . $projectId . '/' . $branch;

        foreach (glob($buildDir . '/*') ?: [] as $file) {
            if (basename($file) === 'build.zip') {
                continue;
            }

            copy($file, $tempDir . '/' . basename($file));
        }

        $tarPath = sys_get_temp_dir() . '/tw-deploy-' . uniqid() . '.tar.gz';

        exec(
            'tar -czf ' . escapeshellarg($tarPath) . ' -C ' . escapeshellarg($tempDir) . ' . 2>&1',
            $tarOut,
            $tarExit,
        );

        $this->removeDirectory($tempDir);

        if ($tarExit !== 0) {
            $this->log($id, $branch, '[Deploy] Failed to create deploy archive: ' . implode("\n", $tarOut));

            return Command::FAILURE;
        }

        $this->log(
            $id,
            $branch,
            sprintf('[Deploy] Uploading build to %s...', $server->host),
        );

        $sftp = new SFTP($server->host, $server->port, 30);

        if (! $sftp->login($credential->username, $key)) {
            unlink($tarPath);
            $this->log($id, $branch, sprintf('[Deploy] SSH login failed for user \'%s\'.', $credential->username));

            return Command::FAILURE;
        }

        $remoteDir = 'tragwerk/' . $projectId . '/' . $branch;
        $sftp->mkdir($remoteDir, -1, true);

        if (! $sftp->put($remoteDir . '/deploy.tar.gz', $tarPath, SFTP::SOURCE_LOCAL_FILE)) {
            unlink($tarPath);
            $this->log($id, $branch, '[Deploy] Failed to upload archive to server.');

            return Command::FAILURE;
        }

        unlink($tarPath);

        $extractResult = $sftp->exec('cd ~/' . $remoteDir . ' && tar xzf deploy.tar.gz && rm deploy.tar.gz 2>&1');

        $extractError = is_string($extractResult) ? $extractResult : '';

        if ($sftp->getExitStatus() !== 0) {
            $this->log($id, $branch, '[Deploy] Failed to extract archive: ' . $extractError);

            return Command::FAILURE;
        }

        $this->log($id, $branch, '[Deploy] Running docker compose up --build -d...');

        $this->streamExec(
            $sftp,
            'cd ~/' . $remoteDir . ' && docker compose up --build -d 2>&1',
            $id,
            $branch,
        );

        $exitStatus = $sftp->getExitStatus();

        if ($exitStatus !== 0) {
            $this->log($id, $branch, sprintf('[Deploy] Deploy failed with exit code %d.', $exitStatus ?? -1));

            return Command::FAILURE;
        }

        $this->log($id, $branch, '[Deploy] Deploy completed successfully.');

        return Command::SUCCESS;
    }

    private function streamExec(SFTP $sftp, string $cmd, ProjectIdentifier $projectId, string $branch): void
    {
        $buffer = '';

        $sftp->exec($cmd, function (string $chunk) use ($projectId, $branch, &$buffer): void {
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line   = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                if (trim($line) === '') {
                    continue;
                }

                $this->log($projectId, $branch, $line);
            }
        });

        if (trim($buffer) === '') {
            return;
        }

        $this->log($projectId, $branch, $buffer);
    }

    private function log(ProjectIdentifier $projectId, string $branch, string $message): void
    {
        $this->buildLogRepository->create(new BuildLog(
            id:        BuildLogIdentifier::create(),
            projectId: $projectId,
            branch:    $branch,
            type:      BuildLogType::BUILD,
            message:   $message,
            createdAt: TimestampImmutable::now(),
        ));
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (glob($path . '/*') ?: [] as $entry) {
            if (is_dir($entry)) {
                $this->removeDirectory($entry);
            } else {
                unlink($entry);
            }
        }

        rmdir($path);
    }
}
