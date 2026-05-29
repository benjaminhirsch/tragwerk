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
use Tragwerk\Domain\Enum\DeployJobStatus;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\DeployJobIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
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
        private readonly DeployJobRepository $deployJobRepository,
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
            ->addArgument('commit-sha', InputArgument::REQUIRED, 'Git commit SHA')
            ->addArgument('deploy-job-id', InputArgument::REQUIRED, 'Deploy job UUID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectId   = $input->getArgument('project-id');
        $branch      = $input->getArgument('branch');
        $commitSha   = $input->getArgument('commit-sha');
        $deployJobId = $input->getArgument('deploy-job-id');

        assert(is_string($projectId));
        assert(is_string($branch));
        assert(is_string($commitSha));
        assert(is_string($deployJobId));

        $id    = ProjectIdentifier::fromString($projectId);
        $jobId = DeployJobIdentifier::fromString($deployJobId);

        $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Running);

        try {
            $project = $this->projectRepository->getById($id);
        } catch (Throwable $e) {
            $this->log($jobId, '[Deploy] Project not found: ' . $e->getMessage());
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        try {
            $server = $this->serverRepository->getById($project->serverId);
        } catch (Throwable $e) {
            $this->log($jobId, '[Deploy] Server not found: ' . $e->getMessage());
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        if ($server->credentialId === null) {
            $this->log($jobId, '[Deploy] No credential assigned to server.');
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        try {
            $credential = $this->credentialRepository->getById($server->credentialId);
        } catch (Throwable $e) {
            $this->log($jobId, '[Deploy] Credential not found: ' . $e->getMessage());
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        if ($credential->privateKey === null) {
            $this->log($jobId, '[Deploy] Credential has no SSH key.');
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        try {
            $key = PublicKeyLoader::loadPrivateKey($credential->privateKey);
        } catch (NoKeyLoadedException $e) {
            $this->log($jobId, '[Deploy] Failed to load SSH key: ' . $e->getMessage());
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

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
            $this->log($jobId, '[Deploy] Failed to export source from git: ' . implode("\n", $archiveOut));
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

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
            $this->log($jobId, '[Deploy] Failed to create deploy archive: ' . implode("\n", $tarOut));
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        $this->log($jobId, sprintf('[Deploy] Uploading build to %s...', $server->host));

        $sftp = new SFTP($server->host, $server->port, 30);

        if (! $sftp->login($credential->username, $key)) {
            unlink($tarPath);
            $this->log($jobId, sprintf('[Deploy] SSH login failed for user \'%s\'.', $credential->username));
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        $remoteDir = 'tragwerk/' . $projectId . '/' . $branch;
        $sftp->mkdir($remoteDir, -1, true);

        if (! $sftp->put($remoteDir . '/deploy.tar.gz', $tarPath, SFTP::SOURCE_LOCAL_FILE)) {
            unlink($tarPath);
            $this->log($jobId, '[Deploy] Failed to upload archive to server.');
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        unlink($tarPath);

        $extractResult = $sftp->exec('cd ~/' . $remoteDir . ' && tar xzf deploy.tar.gz && rm deploy.tar.gz 2>&1');

        $extractError = is_string($extractResult) ? $extractResult : '';

        if ($sftp->getExitStatus() !== 0) {
            $this->log($jobId, '[Deploy] Failed to extract archive: ' . $extractError);
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        $this->log($jobId, '[Deploy] Running docker compose up --build --wait...');

        $this->streamExec(
            $sftp,
            'cd ~/' . $remoteDir . ' && docker compose up --build --wait 2>&1',
            $jobId,
        );

        $exitStatus = $sftp->getExitStatus();

        if ($exitStatus !== 0) {
            $this->streamExec(
                $sftp,
                'cd ~/' . $remoteDir . ' && docker compose logs --tail 50 2>&1',
                $jobId,
            );
            $this->log($jobId, sprintf('[Deploy] Deploy failed with exit code %d.', $exitStatus ?? -1));
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        $this->log($jobId, '[Deploy] Deploy completed successfully.');
        $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Completed);

        return Command::SUCCESS;
    }

    private function streamExec(SFTP $sftp, string $cmd, DeployJobIdentifier $jobId): void
    {
        $buffer = '';

        $sftp->exec($cmd, function (string $chunk) use ($jobId, &$buffer): void {
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line   = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                if (trim($line) === '') {
                    continue;
                }

                $this->log($jobId, $line);
            }
        });

        if (trim($buffer) === '') {
            return;
        }

        $this->log($jobId, $buffer);
    }

    private function log(DeployJobIdentifier $jobId, string $message): void
    {
        $this->deployJobRepository->appendOutput($jobId, $message . "\n");
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
