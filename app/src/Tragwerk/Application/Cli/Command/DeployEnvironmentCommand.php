<?php

declare(strict_types=1);

namespace Tragwerk\Application\Cli\Command;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Mapper\TreeMapper;
use DOMDocument;
use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Exception\NoKeyLoadedException;
use phpseclib3\Net\SFTP;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use Tragwerk\Application\Queue\Message\PruneRegistryImages;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Domain\Config\XmlToArrayConverter;
use Tragwerk\Domain\Docker\DockerComposeGenerator;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Enum\DeployJobStatus;
use Tragwerk\Domain\Enum\MountSource;
use Tragwerk\Domain\Model\ProjectConfig;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\Repository\DomainRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\DeployJobIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Infrastructure\Git\BareRepository;

use function assert;
use function basename;
use function copy;
use function date;
use function escapeshellarg;
use function exec;
use function explode;
use function file_exists;
use function file_get_contents;
use function filter_var;
use function glob;
use function implode;
use function is_array;
use function is_dir;
use function is_int;
use function is_string;
use function mkdir;
use function preg_match;
use function preg_replace;
use function rmdir;
use function rtrim;
use function sleep;
use function str_contains;
use function str_starts_with;
use function strpos;
use function strtolower;
use function substr;
use function sys_get_temp_dir;
use function trim;
use function uniqid;
use function unlink;

use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;

#[AsCommand(name: 'project:deploy', description: 'Deploy a built environment to the target server')]
final class DeployEnvironmentCommand extends Command
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ServerRepository $serverRepository,
        private readonly CredentialRepository $credentialRepository,
        private readonly DeployJobRepository $deployJobRepository,
        private readonly RegistryRepository $registryRepository,
        private readonly DomainRepository $domainRepository,
        private readonly BareRepository $bareRepository,
        private readonly XmlToArrayConverter $xmlConverter,
        private readonly TreeMapper $treeMapper,
        private readonly DockerComposeGenerator $composeGenerator,
        private readonly Producer $producer,
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

        try {
            $registry = $this->registryRepository->getById($project->registryId);
        } catch (Throwable $e) {
            $this->log($jobId, '[Deploy] Registry not found: ' . $e->getMessage());
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        return $this->deployViaRegistry(
            $projectId,
            $branch,
            $commitSha,
            $jobId,
            $acmeEmail,
            $project,
            $server,
            $credential,
            $key,
            $registry,
        );
    }

    /**
     * Phase-2 deploy: build the Docker image locally, push to registry, then pull+swap on VPS.
     */
    private function deployViaRegistry(
        string $projectId,
        string $branch,
        string $commitSha,
        DeployJobIdentifier $jobId,
        string $acmeEmail,
        Project $project,
        Server $server,
        Credential $credential,
        PrivateKey $key,
        Registry $registry,
    ): int {
        $branchSlug     = $this->slugify(basename($branch));
        $projectSlug    = $this->slugify($project->name);
        $shortSha       = substr($commitSha, 0, 8);
        $timestamp      = date('YmdHis');
        $repoPath       = $this->bareRepository->getPath($projectId);
        $composeProject = 'tw-' . substr($projectId, 0, 8) . '-' . $branchSlug;

        // 1. Export source from git into a temp directory
        $buildDir = sys_get_temp_dir() . '/tw-build-' . uniqid();
        mkdir($buildDir, 0755, true);

        exec(
            'git -C ' . escapeshellarg($repoPath)
            . ' archive ' . escapeshellarg($commitSha)
            . ' | tar xf - -C ' . escapeshellarg($buildDir) . ' 2>&1',
            $archiveOut,
            $archiveExit,
        );

        if ($archiveExit !== 0) {
            $this->removeDirectory($buildDir);
            $this->log($jobId, '[Deploy] Failed to export source: ' . implode("\n", $archiveOut));
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        // Copy generated Dockerfiles/Caddyfiles from build artifacts
        $artifactsDir = rtrim($this->projectDataPath, '/') . '/' . $projectId . '/' . $branch;
        foreach (glob($artifactsDir . '/*') ?: [] as $file) {
            if (basename($file) === 'build.zip') {
                continue;
            }

            copy($file, $buildDir . '/' . basename($file));
        }

        // 2. Parse config to find applications
        $xmlContent = $this->bareRepository->getFileContent($projectId, $commitSha, '.tragwerk/config.xml');
        if ($xmlContent === null || $xmlContent === '') {
            $this->removeDirectory($buildDir);
            $this->log($jobId, '[Deploy] No .tragwerk/config.xml found.');
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        try {
            $config = $this->parseProjectConfig($xmlContent);
        } catch (Throwable $e) {
            $this->removeDirectory($buildDir);
            $this->log($jobId, '[Deploy] Config parse error: ' . $e->getMessage());
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        // 3. docker login on local host
        $dockerConfigDir = sys_get_temp_dir() . '/tw-docker-' . uniqid();
        mkdir($dockerConfigDir, 0700, true);
        $dockerEnv = ['DOCKER_CONFIG' => $dockerConfigDir, 'DOCKER_BUILDKIT' => '1'];

        $this->log($jobId, '[Deploy] Logging in to registry ' . $registry->url . '...');
        $login = new Process(
            ['docker', 'login', $registry->url, '-u', $registry->username, '--password-stdin'],
            env: $dockerEnv,
        );
        $login->setInput($registry->password);
        $login->run();

        if (! $login->isSuccessful()) {
            $this->removeDirectory($buildDir);
            $this->removeDirectory($dockerConfigDir);
            $this->log($jobId, '[Deploy] Registry login failed: ' . $login->getErrorOutput());
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        // 4. Build + push each application image
        /** @var array<string, string> $imageTags appSlug → full image ref */
        $imageTags = [];

        foreach ($config->applications as $app) {
            $appSlug    = $this->slugify($app->name);
            $imageTag   = $registry->url . '/' . $registry->repository
                . ':' . $appSlug . '-' . $branchSlug . '-' . $timestamp . '-' . $shortSha;
            $cacheTag   = $registry->url . '/' . $registry->repository
                . ':' . $appSlug . '-' . $branchSlug . '-cache';
            $dockerfile = $buildDir . '/Dockerfile.' . $appSlug;

            $this->log($jobId, '[Deploy] Building image: ' . $imageTag);

            $build = new Process(
                [
                    'docker',
                    'build',
                    '-f',
                    $dockerfile,
                    '-t',
                    $imageTag,
                    '--cache-from',
                    $cacheTag,
                    '--build-arg',
                    'BUILDKIT_INLINE_CACHE=1',
                    '.',
                ],
                $buildDir,
                $dockerEnv,
                timeout: null,
            );

            $buildOutput = '';
            $build->run(static function (string $type, string $buf) use (&$buildOutput): void {
                $buildOutput .= $buf;
            });

            foreach (explode("\n", trim($buildOutput)) as $line) {
                if (trim($line) === '') {
                    continue;
                }

                $this->log($jobId, $line);
            }

            if (! $build->isSuccessful()) {
                $this->removeDirectory($buildDir);
                (new Process(['docker', 'logout', $registry->url], env: $dockerEnv))->run();
                $this->removeDirectory($dockerConfigDir);
                $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

                return Command::FAILURE;
            }

            $this->log($jobId, '[Deploy] Pushing image: ' . $imageTag);
            $push = new Process(['docker', 'push', $imageTag], env: $dockerEnv, timeout: null);
            $push->run(function (string $type, string $buf) use ($jobId): void {
                foreach (explode("\n", trim($buf)) as $line) {
                    if (trim($line) === '') {
                        continue;
                    }

                    $this->log($jobId, $line);
                }
            });

            if (! $push->isSuccessful()) {
                $this->removeDirectory($buildDir);
                (new Process(['docker', 'logout', $registry->url], env: $dockerEnv))->run();
                $this->removeDirectory($dockerConfigDir);
                $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

                return Command::FAILURE;
            }

            // Push cache tag so the next build can reuse layers via --cache-from.
            (new Process(['docker', 'tag', $imageTag, $cacheTag], env: $dockerEnv))->run();
            (new Process(['docker', 'push', $cacheTag], env: $dockerEnv, timeout: null))->run();

            (new Process(['docker', 'rmi', $imageTag, $cacheTag], env: $dockerEnv))->run();
            $imageTags[$appSlug] = $imageTag;
        }

        $this->removeDirectory($buildDir);
        (new Process(['docker', 'logout', $registry->url], env: $dockerEnv))->run();
        $this->removeDirectory($dockerConfigDir);

        // 5. SSH to VPS — login + pull + swap
        $sftpHost = filter_var($server->host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            ? '[' . $server->host . ']'
            : $server->host;
        $sftp     = new SFTP($sftpHost, $server->port, 30);

        if (! $sftp->login($credential->username, $key)) {
            $this->log($jobId, '[Deploy] SSH login failed.');
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        $sftp->setTimeout(0);
        $remoteDir = 'tragwerk/' . $projectId . '/' . $branch;
        $sftp->mkdir($remoteDir, -1, true);

        // Upload docker-compose.yml (with image: refs) + Caddyfiles
        $domainsByPlaceholder = [];
        $projectIdentifier    = ProjectIdentifier::fromString($projectId);
        foreach ($this->domainRepository->findByEnvironment($projectIdentifier, $branch) as $domain) {
            $domainsByPlaceholder[$domain->placeholder][] = $domain->host;
        }

        $compose = $this->composeGenerator->generate($config, $branch, $domainsByPlaceholder, $imageTags, $projectSlug);
        $sftp->put(
            $remoteDir . '/docker-compose.yml',
            Yaml::dump($compose, 8, 2, Yaml::DUMP_NULL_AS_TILDE),
        );

        foreach ($config->applications as $app) {
            $appSlug   = $this->slugify($app->name);
            $caddyFile = $artifactsDir . '/Caddyfile.' . $appSlug;
            if (! file_exists($caddyFile)) {
                continue;
            }

            $content = file_get_contents($caddyFile);
            if ($content === false) {
                continue;
            }

            $sftp->put($remoteDir . '/Caddyfile.' . $appSlug, $content);
        }

        $this->log($jobId, '[Deploy] Logging in to registry on VPS...');
        $sftp->exec(
            'echo ' . escapeshellarg($registry->password)
            . ' | docker login ' . escapeshellarg($registry->url)
            . ' -u ' . escapeshellarg($registry->username)
            . ' --password-stdin 2>&1',
        );

        $dc = 'NO_COLOR=1 docker compose --project-name ' . escapeshellarg($composeProject) . ' -f docker-compose.yml';

        $sftp->exec('docker network create tragwerk-net 2>/dev/null; true');

        $this->log($jobId, '[Deploy] Pulling image on VPS...');
        $sftp->exec('cd ~/' . $remoteDir . ' && ' . $dc . ' pull 2>&1');

        $this->log($jobId, '[Deploy] Ensuring DB is running...');
        $sftp->exec('cd ~/' . $remoteDir . ' && ' . $dc . ' up db --wait --no-deps -d 2>&1');

        // Blue/green swap: start new container alongside old, wait for healthy, then stop old.
        $composeDefaultNetwork = $composeProject . '_default';
        $newContainers         = [];

        foreach ($config->applications as $app) {
            $appSlug         = $this->slugify($app->name);
            $svcRawUnchecked = $compose['services'][$appSlug] ?? [];
            assert(is_array($svcRawUnchecked));
            /** @var array<string, mixed> $svcRaw */
            $svcRaw       = $svcRawUnchecked;
            $newContainer = $this->blueGreenSwap(
                $sftp,
                $remoteDir,
                $composeProject,
                $appSlug,
                $imageTags[$appSlug],
                $svcRaw,
                $composeDefaultNetwork,
                $jobId,
            );

            if ($newContainer === null) {
                $sftp->exec('docker logout ' . escapeshellarg($registry->url) . ' 2>/dev/null; true');
                $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

                return Command::FAILURE;
            }

            $newContainers[] = $newContainer;
        }

        $sftp->exec('docker logout ' . escapeshellarg($registry->url) . ' 2>/dev/null; true');
        $this->ensureServerTraefik($sftp, $acmeEmail, $jobId);

        $this->syncFromParentIfFirstDeploy($sftp, $projectId, $branch, $commitSha, $jobId);
        $this->log($jobId, '[Deploy] Registry-based deploy completed.');
        $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Completed);

        if ($registry->pruningEnabled) {
            foreach ($config->applications as $app) {
                $this->producer->sendMessage(new PruneRegistryImages(
                    $registry->id->toString(),
                    $this->slugify($app->name),
                    $branchSlug,
                ));
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Start a new container alongside the currently running one, wait for it to become healthy,
     * then stop and remove the old container. Returns the new container name, or null on failure.
     *
     * @param array<string, mixed> $svc
     */
    private function blueGreenSwap(
        SFTP $sftp,
        string $remoteDir,
        string $composeProject,
        string $appSlug,
        string $imageTag,
        array $svc,
        string $composeDefaultNetwork,
        DeployJobIdentifier $jobId,
    ): string|null {
        $slotFile    = '~/' . $remoteDir . '/.slot-' . $appSlug;
        $currentSlot = trim((string) $sftp->exec('cat ' . $slotFile . ' 2>/dev/null'));
        $newSlot     = $currentSlot === 'a' ? 'b' : 'a';

        $newContainer = $composeProject . '-' . $appSlug . '-' . $newSlot;

        if ($currentSlot === 'a' || $currentSlot === 'b') {
            $oldContainer = $composeProject . '-' . $appSlug . '-' . $currentSlot;
        } else {
            // First blue/green deploy or migration from compose-managed container.
            $oldContainer = trim((string) $sftp->exec(
                'docker ps --filter ' . escapeshellarg('name=' . $composeProject . '-' . $appSlug)
                . ' --format "{{.Names}}" | head -1 2>/dev/null',
            ));
        }

        // Remove stale container from a previous failed deploy.
        $sftp->exec('docker rm -f ' . escapeshellarg($newContainer) . ' 2>/dev/null; true');

        // Use docker create + network connect + start so both networks are attached
        // before the entrypoint runs — otherwise DB hostname 'db' is unresolvable.
        $workingDir = '/root/' . $remoteDir;
        $cmd        = 'docker create --restart unless-stopped'
            . ' --name ' . escapeshellarg($newContainer)
            . ' --network tragwerk-net'
            . ' --label ' . escapeshellarg('tragwerk.working_dir=' . $workingDir);

        /** @var array<string, string> $environment */
        $environment = is_array($svc['environment'] ?? null) ? $svc['environment'] : [];
        foreach ($environment as $key => $value) {
            $cmd .= ' --env ' . escapeshellarg($key . '=' . $value);
        }

        /** @var list<string> $labels */
        $labels = is_array($svc['labels'] ?? null) ? $svc['labels'] : [];
        foreach ($labels as $label) {
            $cmd .= ' --label ' . escapeshellarg($label);
        }

        /** @var list<string> $volumes */
        $volumes = is_array($svc['volumes'] ?? null) ? $svc['volumes'] : [];
        foreach ($volumes as $volume) {
            $cmd .= ' -v ' . escapeshellarg($volume);
        }

        /** @var list<string> $tmpfs */
        $tmpfs = is_array($svc['tmpfs'] ?? null) ? $svc['tmpfs'] : [];
        foreach ($tmpfs as $path) {
            $cmd .= ' --tmpfs ' . escapeshellarg($path);
        }

        /** @var array<string, mixed> $hc */
        $hc = is_array($svc['healthcheck'] ?? null) ? $svc['healthcheck'] : [];
        if ($hc !== [] && isset($hc['test'][1]) && is_string($hc['test'][1])) {
            $interval    = is_string($hc['interval'] ?? null) ? $hc['interval'] : '5s';
            $timeout     = is_string($hc['timeout'] ?? null) ? $hc['timeout'] : '6s';
            $retries     = is_int($hc['retries'] ?? null) ? (string) $hc['retries'] : '12';
            $startPeriod = is_string($hc['start_period'] ?? null) ? $hc['start_period'] : '30s';
            $cmd        .= ' --health-cmd ' . escapeshellarg($hc['test'][1])
                . ' --health-interval ' . escapeshellarg($interval)
                . ' --health-timeout ' . escapeshellarg($timeout)
                . ' --health-retries ' . escapeshellarg($retries)
                . ' --health-start-period ' . escapeshellarg($startPeriod);
        }

        if ($svc['read_only'] === true) {
            $cmd .= ' --read-only';
        }

        $cmd .= ' ' . escapeshellarg($imageTag) . ' 2>&1';

        $this->log($jobId, '[Deploy] Starting ' . $newContainer . '...');
        $createResult = (string) $sftp->exec($cmd);

        if ($sftp->getExitStatus() !== 0) {
            $this->log($jobId, '[Deploy] Failed to create ' . $newContainer . ': ' . $createResult);

            return null;
        }

        // Connect to compose default network before starting so DB hostname resolves on boot.
        $netConnectOut = trim((string) $sftp->exec(
            'docker network connect ' . escapeshellarg($composeDefaultNetwork)
            . ' ' . escapeshellarg($newContainer) . ' 2>&1 || true',
        ));
        if ($netConnectOut !== '' && ! str_contains($netConnectOut, 'already exists')) {
            $this->log($jobId, '[Deploy] Warning: network connect failed: ' . $netConnectOut);
        }

        $sftp->exec('docker start ' . escapeshellarg($newContainer) . ' 2>&1');

        $this->log($jobId, '[Deploy] Waiting for ' . $newContainer . ' to be healthy...');

        $status = '';
        for ($i = 0; $i < 120; $i++) {
            sleep(5);
            $status = trim((string) $sftp->exec(
                'docker inspect --format "{{.State.Health.Status}}" '
                . escapeshellarg($newContainer) . ' 2>/dev/null',
            ));

            if ($status === 'healthy' || $status === 'unhealthy') {
                break;
            }
        }

        if ($status !== 'healthy') {
            $this->log($jobId, '[Deploy] ' . $newContainer . ' did not become healthy (status: ' . $status . ').');
            $this->streamExec($sftp, 'docker logs --tail 100 ' . escapeshellarg($newContainer) . ' 2>&1', $jobId);
            $sftp->exec('docker rm -f ' . escapeshellarg($newContainer) . ' 2>/dev/null; true');

            return null;
        }

        // Collect startup logs and check for critical errors before cutting over.
        $startupLogs = (string) $sftp->exec(
            'docker logs --tail 500 ' . escapeshellarg($newContainer) . ' 2>&1',
        );
        $this->log($jobId, $startupLogs);

        if ($this->logsContainError($startupLogs)) {
            $this->log($jobId, '[Deploy] Errors detected in startup logs — rolling back.');
            $sftp->exec('docker rm -f ' . escapeshellarg($newContainer) . ' 2>/dev/null; true');

            return null;
        }

        $this->log($jobId, '[Deploy] ' . $newContainer . ' is healthy. Removing old containers...');

        // Remove ALL containers matching this app's name pattern except the new one.
        // Catches both compose-managed (e.g. main-tragwerk-1) and previous blue/green
        // containers that survived strategy switches.
        $newId = '$(docker inspect --format "{{.Id}}" ' . escapeshellarg($newContainer) . ' 2>/dev/null | cut -c1-12)';
        $sftp->exec(
            'docker ps -aq --filter ' . escapeshellarg('name=' . $composeProject . '-' . $appSlug)
            . ' | grep -v ' . $newId
            . ' | xargs -r docker rm -f 2>/dev/null; true',
        );

        $sftp->exec('echo ' . escapeshellarg($newSlot) . ' > ' . $slotFile);

        return $newContainer;
    }

    private function logsContainError(string $logs): bool
    {
        $errorPatterns = [
            '/^In .+\.php line \d+:/m',   // PHP/Doctrine exception header
            '/Uncaught .+Exception/i',
            '/PHP Fatal error/i',
            '/\[CRITICAL\]/i',
            '/returned with error code \d+/i',
            '/"level":"error"/i',
        ];

        foreach ($errorPatterns as $pattern) {
            if (preg_match($pattern, $logs) === 1) {
                return true;
            }
        }

        return false;
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
        $parentSlug           = $this->slugify(basename($parentBranch));
        $branchSlug           = $this->slugify(basename($branch));
        $parentDir            = 'tragwerk/' . $projectId . '/' . $parentBranch;
        $childDir             = 'tragwerk/' . $projectId . '/' . $branch;
        $shortId              = substr($projectId, 0, 8);
        $parentComposeProject = 'tw-' . $shortId . '-' . $parentSlug;
        $childComposeProject  = 'tw-' . $shortId . '-' . $branchSlug;

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
            $src         = $parentComposeProject . '_' . $volName;
            $dst         = $childComposeProject . '_' . $volName;

            $this->log($jobId, '[Sync] Syncing DB volume "' . $volName . '" from parent...');

            $parentDc = 'NO_COLOR=1 docker compose --project-name ' . escapeshellarg($parentComposeProject);
            $childDc  = 'NO_COLOR=1 docker compose --project-name ' . escapeshellarg($childComposeProject);

            $sftp->exec('cd ~/' . $parentDir . ' && ' . $parentDc . ' stop ' . escapeshellarg($serviceSlug) . ' 2>&1');
            $sftp->exec('cd ~/' . $childDir . ' && ' . $childDc . ' stop ' . escapeshellarg($serviceSlug) . ' 2>&1');

            $sftp->exec(
                'docker run --rm'
                . ' -v ' . escapeshellarg($src) . ':/src:ro'
                . ' -v ' . escapeshellarg($dst) . ':/dst'
                . ' alpine sh -c "cp -a /src/. /dst/" 2>&1',
            );

            $sftp->exec('cd ~/' . $parentDir . ' && ' . $parentDc . ' start ' . escapeshellarg($serviceSlug) . ' 2>&1');
            $sftp->exec('cd ~/' . $childDir . ' && ' . $childDc . ' start ' . escapeshellarg($serviceSlug) . ' 2>&1');

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
                $src       = $parentComposeProject . '_' . $volName;
                $dst       = $childComposeProject . '_' . $volName;

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
