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
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
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
use function escapeshellarg;
use function implode;
use function is_string;
use function preg_replace;
use function sprintf;
use function str_starts_with;
use function strtolower;

#[AsCommand(name: 'project:sync-data', description: 'Sync data volumes from parent branch to a child environment')]
final class SyncEnvironmentDataCommand extends Command
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ServerRepository $serverRepository,
        private readonly CredentialRepository $credentialRepository,
        private readonly DeployJobRepository $deployJobRepository,
        private readonly BareRepository $bareRepository,
        private readonly XmlToArrayConverter $xmlConverter,
        private readonly TreeMapper $treeMapper,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('project-id', InputArgument::REQUIRED, 'Project UUID')
            ->addArgument('branch', InputArgument::REQUIRED, 'Git branch name')
            ->addArgument('deploy-job-id', InputArgument::REQUIRED, 'Deploy job UUID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectId   = $input->getArgument('project-id');
        $branch      = $input->getArgument('branch');
        $deployJobId = $input->getArgument('deploy-job-id');

        assert(is_string($projectId));
        assert(is_string($branch));
        assert(is_string($deployJobId));

        $id    = ProjectIdentifier::fromString($projectId);
        $jobId = DeployJobIdentifier::fromString($deployJobId);

        $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Running);

        try {
            $project = $this->projectRepository->getById($id);
        } catch (Throwable $e) {
            $this->log($jobId, '[Sync] Project not found: ' . $e->getMessage());
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        assert($project instanceof Project);

        try {
            $server = $this->serverRepository->getById($project->serverId);
        } catch (Throwable $e) {
            $this->log($jobId, '[Sync] Server not found: ' . $e->getMessage());
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        assert($server instanceof Server);

        if ($server->credentialId === null) {
            $this->log($jobId, '[Sync] No credential assigned to server.');
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        try {
            $credential = $this->credentialRepository->getById($server->credentialId);
        } catch (Throwable $e) {
            $this->log($jobId, '[Sync] Credential not found: ' . $e->getMessage());
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        assert($credential instanceof Credential);

        if ($credential->privateKey === null) {
            $this->log($jobId, '[Sync] Credential has no SSH key.');
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        try {
            $key = PublicKeyLoader::loadPrivateKey($credential->privateKey);
        } catch (NoKeyLoadedException $e) {
            $this->log($jobId, '[Sync] Failed to load SSH key: ' . $e->getMessage());
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        if (! $this->deployJobRepository->hasCompletedDeploy($id, $branch)) {
            $this->log($jobId, '[Sync] Environment not yet deployed — nothing to sync into.');
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        $parents      = $this->bareRepository->getBranchParents($projectId);
        $parentBranch = $parents[$branch] ?? null;

        if ($parentBranch === null) {
            $this->log($jobId, '[Sync] No parent branch found for "' . $branch . '".');
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        if (! $this->deployJobRepository->hasCompletedDeploy($id, $parentBranch)) {
            $this->log($jobId, '[Sync] Parent branch "' . $parentBranch . '" has no completed deploy.');
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        try {
            $commits = $this->bareRepository->getCommits($projectId, $branch, 1);
        } catch (Throwable) {
            $commits = [];
        }

        if ($commits === []) {
            $this->log($jobId, '[Sync] No commits found on branch "' . $branch . '".');
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        $xmlContent = $this->bareRepository->getFileContent($projectId, $commits[0]->hash, '.tragwerk/config.xml');

        if ($xmlContent === null || $xmlContent === '') {
            $this->log($jobId, '[Sync] No .tragwerk/config.xml found — nothing to sync.');
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        try {
            $config = $this->parseConfig($xmlContent);
        } catch (RuntimeException $e) {
            $this->log($jobId, '[Sync] Config parse error: ' . $e->getMessage());
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        $sftp = new SFTP($server->host, $server->port, 30);

        if (! $sftp->login($credential->username, $key)) {
            $this->log($jobId, sprintf('[Sync] SSH login failed for user \'%s\'.', $credential->username));
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        $sftp->setTimeout(0);

        $this->log($jobId, '[Sync] Syncing data volumes from parent branch "' . $parentBranch . '"...');

        try {
            $this->syncVolumes($sftp, $projectId, $branch, $parentBranch, $config, $jobId);
        } catch (Throwable $e) {
            $this->log($jobId, '[Sync] Error during sync: ' . $e->getMessage());
            $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Failed);

            return Command::FAILURE;
        }

        $this->log($jobId, '[Sync] Data sync completed successfully.');
        $this->deployJobRepository->updateStatus($jobId, DeployJobStatus::Completed);

        return Command::SUCCESS;
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

    private function parseConfig(string $xmlContent): ProjectConfig
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

    private function log(DeployJobIdentifier $jobId, string $message): void
    {
        $this->deployJobRepository->appendOutput($jobId, $message . "\n");
    }
}
