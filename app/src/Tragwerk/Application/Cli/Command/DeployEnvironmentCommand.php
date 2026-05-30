<?php

declare(strict_types=1);

namespace Tragwerk\Application\Cli\Command;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Mapper\TreeMapper;
use DOMDocument;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Exception\NoKeyLoadedException;
use phpseclib3\Net\SFTP;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Tragwerk\Domain\Config\XmlToArrayConverter;
use Tragwerk\Domain\Enum\DeployJobStatus;
use Tragwerk\Domain\Enum\MountSource;
use Tragwerk\Domain\Model\ProjectConfig;
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
use function preg_replace;
use function rmdir;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function strpos;
use function strtolower;
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
        private readonly XmlToArrayConverter $xmlConverter,
        private readonly TreeMapper $treeMapper,
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
            ->addArgument('deploy-job-id', InputArgument::REQUIRED, 'Deploy job UUID')
            ->addArgument('acme-email', InputArgument::OPTIONAL, 'ACME email for Traefik', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectId   = $input->getArgument('project-id');
        $branch      = $input->getArgument('branch');
        $commitSha   = $input->getArgument('commit-sha');
        $deployJobId = $input->getArgument('deploy-job-id');
        $acmeEmail   = $input->getArgument('acme-email') ?? '';

        assert(is_string($projectId));
        assert(is_string($branch));
        assert(is_string($commitSha));
        assert(is_string($deployJobId));
        assert(is_string($acmeEmail));

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

        // Remove any docker-related files from the repo source so they cannot
        // conflict with or supplement the generated Dockerfiles and compose config.
        foreach (glob($tempDir . '/docker-compose*.yml') ?: [] as $file) {
            unlink($file);
        }

        foreach (glob($tempDir . '/docker-compose*.yaml') ?: [] as $file) {
            unlink($file);
        }

        foreach (glob($tempDir . '/Dockerfile*') ?: [] as $file) {
            unlink($file);
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

        // Disable the cumulative timeout — exec() resets curTimeout to $this->timeout on each call,
        // and docker compose build can run silently for many minutes (layer pulls, compilation).
        $sftp->setTimeout(0);

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

        // Create the shared Traefik network before docker compose up so external network refs resolve
        $sftp->exec('docker network create tragwerk-net 2>/dev/null; true');

        $this->log($jobId, '[Deploy] Running docker compose up --build --wait...');

        $dc = 'NO_COLOR=1 docker compose -f docker-compose.yml';

        try {
            $this->streamExec(
                $sftp,
                'cd ~/' . $remoteDir . ' && ' . $dc . ' up --build --wait 2>&1',
                $jobId,
            );
        } catch (Throwable $e) {
            $this->log($jobId, '[Deploy] SSH error during deploy: ' . $e->getMessage());
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        $exitStatus = $sftp->getExitStatus();

        // Always capture container logs — deploy hook output (e.g. migrations) goes there
        // because --wait implies detached mode, so container stdout is not part of the up stream.
        $this->streamExec($sftp, 'cd ~/' . $remoteDir . ' && ' . $dc . ' logs --no-color --tail 500 2>&1', $jobId);

        // Ensure server-level Traefik is running after docker compose up (which may have stopped
        // a project-level Traefik from an older compose that included it).
        $this->ensureServerTraefik($sftp, $acmeEmail, $jobId);

        if ($exitStatus !== 0) {
            $code = $exitStatus ?? -1;
            $this->log($jobId, sprintf('[Deploy] Deploy failed (exit code %d). Collecting diagnostics...', $code));
            $this->streamExec($sftp, 'cd ~/' . $remoteDir . ' && ' . $dc . ' ps 2>&1', $jobId);
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        $this->syncFromParentIfFirstDeploy($sftp, $projectId, $branch, $commitSha, $jobId);

        $this->log($jobId, '[Deploy] Deploy completed successfully.');
        $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Completed);

        return Command::SUCCESS;
    }

    private function syncFromParentIfFirstDeploy(
        SFTP $sftp,
        string $projectId,
        string $branch,
        string $commitSha,
        DeployJobIdentifier $jobId,
    ): void {
        $id = ProjectIdentifier::fromString($projectId);

        if ($this->deployJobRepository->hasCompletedDeploy($id, $branch)) {
            return;
        }

        $parents      = $this->bareRepository->getBranchParents($projectId);
        $parentBranch = $parents[$branch] ?? null;

        if ($parentBranch === null) {
            return;
        }

        if (! $this->deployJobRepository->hasCompletedDeploy($id, $parentBranch)) {
            return;
        }

        $xmlContent = $this->bareRepository->getFileContent($projectId, $commitSha, '.tragwerk/config.xml');

        if ($xmlContent === null || $xmlContent === '') {
            return;
        }

        try {
            $config = $this->parseProjectConfig($xmlContent);
        } catch (Throwable) {
            return;
        }

        $this->log($jobId, '[Sync] Syncing data volumes from parent branch "' . $parentBranch . '"...');

        $this->syncVolumes($sftp, $projectId, $branch, $parentBranch, $config, $jobId);

        $this->log($jobId, '[Sync] Data sync completed.');
    }

    private function syncVolumes(
        SFTP $sftp,
        string $projectId,
        string $branch,
        string $parentBranch,
        ProjectConfig $config,
        DeployJobIdentifier $jobId,
    ): void {
        $parentSlug = $this->slugify(basename($parentBranch));
        $branchSlug = $this->slugify(basename($branch));
        $parentDir  = 'tragwerk/' . $projectId . '/' . $parentBranch;
        $childDir   = 'tragwerk/' . $projectId . '/' . $branch;

        foreach ($config->services as $service) {
            $type = $service->type->value;

            if (
                ! str_starts_with($type, 'postgresql:')
                && ! str_starts_with($type, 'mysql:')
                && ! str_starts_with($type, 'mariadb:')
            ) {
                continue;
            }

            $serviceSlug = $this->slugify($service->name);
            $volName     = $serviceSlug . '-data';
            $src         = $parentSlug . '_' . $volName;
            $dst         = $branchSlug . '_' . $volName;

            $this->log($jobId, '[Sync] Syncing DB volume "' . $volName . '" from parent...');

            $sftp->exec(
                'cd ~/' . $parentDir . ' && NO_COLOR=1 docker compose stop '
                . escapeshellarg($serviceSlug) . ' 2>&1',
            );
            $sftp->exec(
                'cd ~/' . $childDir . ' && NO_COLOR=1 docker compose stop '
                . escapeshellarg($serviceSlug) . ' 2>&1',
            );

            $sftp->exec(
                'docker run --rm'
                . ' -v ' . escapeshellarg($src) . ':/src:ro'
                . ' -v ' . escapeshellarg($dst) . ':/dst'
                . ' alpine sh -c "cp -a /src/. /dst/" 2>&1',
            );

            $sftp->exec(
                'cd ~/' . $parentDir . ' && NO_COLOR=1 docker compose start '
                . escapeshellarg($serviceSlug) . ' 2>&1',
            );
            $sftp->exec(
                'cd ~/' . $childDir . ' && NO_COLOR=1 docker compose start '
                . escapeshellarg($serviceSlug) . ' 2>&1',
            );

            $this->log($jobId, '[Sync] Volume "' . $volName . '" synced.');
        }

        foreach ($config->applications as $app) {
            $appSlug = $this->slugify($app->name);

            foreach ($app->mounts as $mount) {
                if ($mount->source !== MountSource::LOCAL || ! $mount->cloneFromParent) {
                    continue;
                }

                $mountSlug = $this->slugify($mount->name);
                $volName   = $appSlug . '-' . $mountSlug;
                $src       = $parentSlug . '_' . $volName;
                $dst       = $branchSlug . '_' . $volName;

                $this->log($jobId, '[Sync] Syncing app mount volume "' . $volName . '"...');

                $sftp->exec(
                    'docker run --rm'
                    . ' -v ' . escapeshellarg($src) . ':/src:ro'
                    . ' -v ' . escapeshellarg($dst) . ':/dst'
                    . ' alpine sh -c "cp -a /src/. /dst/" 2>&1',
                );

                $this->log($jobId, '[Sync] Volume "' . $volName . '" synced.');
            }
        }
    }

    private function parseProjectConfig(string $xmlContent): ProjectConfig
    {
        $dom = new DOMDocument();

        if (! $dom->loadXML($xmlContent)) {
            throw new RuntimeException('Invalid XML');
        }

        $source = $this->xmlConverter->convert($dom);
        unset($source['xsi:noNamespaceSchemaLocation']);

        try {
            return $this->treeMapper->map(ProjectConfig::class, $source);
        } catch (MappingError $e) {
            $errors = [];
            foreach ($e->messages() as $msg) {
                $errors[] = $msg->path() . ': ' . $msg->toString();
            }

            throw new RuntimeException('Config mapping failed: ' . implode(', ', $errors));
        }
    }

    private function slugify(string $name): string
    {
        $slug = preg_replace('/[\s_]+/', '-', strtolower($name)) ?? '';

        return preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';
    }

    private function ensureServerTraefik(SFTP $sftp, string $acmeEmail, DeployJobIdentifier $jobId): void
    {
        $running = trim((string) $sftp->exec(
            'docker inspect --format "{{.State.Running}}" tragwerk-traefik 2>/dev/null',
        ));

        if ($running === 'true') {
            return;
        }

        $this->log($jobId, '[Deploy] Starting server-level Traefik...');

        // Stop any project-compose Traefik containers that may still hold ports 80/443
        $sftp->exec(
            'docker ps --filter "name=traefik" --format "{{.Names}}"'
            . ' | grep -v "^tragwerk-traefik$"'
            . ' | xargs -r docker stop 2>/dev/null; true',
        );

        // Remove stale managed Traefik container (stopped state)
        $sftp->exec('docker rm -f tragwerk-traefik 2>/dev/null; true');

        $emailFlag = escapeshellarg('--certificatesresolvers.letsencrypt.acme.email=' . $acmeEmail);

        $result = $sftp->exec(
            'docker run -d'
            . ' --name tragwerk-traefik'
            . ' --restart unless-stopped'
            . ' --network tragwerk-net'
            . ' -v /var/run/docker.sock:/var/run/docker.sock:ro'
            . ' -v tragwerk-traefik-certs:/certs'
            . ' -p 80:80'
            . ' -p 443:443'
            . ' traefik:v3'
            . ' --providers.docker=true'
            . ' --providers.docker.exposedbydefault=false'
            . ' --providers.docker.network=tragwerk-net'
            . ' --entrypoints.web.address=:80'
            . ' --entrypoints.web.http.redirections.entrypoint.to=websecure'
            . ' --entrypoints.web.http.redirections.entrypoint.scheme=https'
            . ' --entrypoints.web.http.redirections.entrypoint.permanent=true'
            . ' --entrypoints.websecure.address=:443'
            . ' --certificatesresolvers.letsencrypt.acme.tlschallenge=true'
            . ' ' . $emailFlag
            . ' --certificatesresolvers.letsencrypt.acme.storage=/certs/acme.json'
            . ' 2>&1',
        );

        if ($sftp->getExitStatus() !== 0) {
            $this->log($jobId, '[Deploy] Warning: Could not start Traefik: ' . (string) $result);

            return;
        }

        $this->log($jobId, '[Deploy] Server-level Traefik started.');
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
