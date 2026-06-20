<?php

declare(strict_types=1);

namespace Tragwerk\Application\Queue\Handler;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Mapper\TreeMapper;
use DOMDocument;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use Tragwerk\Application\Queue\Message;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Application\Service\BranchAncestorResolver;
use Tragwerk\Domain\Config\XmlToArrayConverter;
use Tragwerk\Domain\Docker\DockerComposeGenerator;
use Tragwerk\Domain\Docker\DockerfileGenerator;
use Tragwerk\Domain\Entity\BuildLog;
use Tragwerk\Domain\Entity\DeployJob;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Enum\BuildLogType;
use Tragwerk\Domain\Enum\DeployJobStatus;
use Tragwerk\Domain\Event\BuildLogCreated;
use Tragwerk\Domain\Event\DeployJobCreated;
use Tragwerk\Domain\Model\ProjectConfig;
use Tragwerk\Domain\Repository\DomainRepository;
use Tragwerk\Domain\Repository\EnvVarRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\BuildLogIdentifier;
use Tragwerk\Domain\ValueObject\DeployJobIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Infrastructure\Git\BareRepository;
use ZipArchive;

use function array_flip;
use function assert;
use function basename;
use function chmod;
use function file_put_contents;
use function glob;
use function implode;
use function is_dir;
use function iterator_to_array;
use function mkdir;
use function rtrim;
use function usort;

use const PHP_INT_MAX;

final readonly class BuildEnvironment
{
    public function __construct(
        private BareRepository $bareRepository,
        private XmlToArrayConverter $xmlConverter,
        private TreeMapper $treeMapper,
        private DockerComposeGenerator $composeGenerator,
        private DockerfileGenerator $dockerfileGenerator,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private string $projectDataPath,
        private Producer $producer,
        private DomainRepository $domainRepository,
        private ProjectRepository $projectRepository,
        private TeamRepository $teamRepository,
        private UserRepository $userRepository,
        private LockFactory $lockFactory,
        private EnvVarRepository $envVarRepository,
        private BranchAncestorResolver $ancestorResolver,
    ) {
    }

    public function handle(Message\BuildEnvironment $message): void
    {
        $projectId = $message->projectId;
        $branch    = $message->branch;
        $commitSha = $message->commitSha;

        $lock = $this->lockFactory->createLock('build:' . $projectId . ':' . $branch, ttl: 600.0);
        $lock->acquire(blocking: true);

        try {
            $this->doBuild($projectId, $branch, $commitSha);
        } finally {
            $lock->release();
        }
    }

    private function doBuild(string $projectId, string $branch, string $commitSha): void
    {
        $this->logger->info('Starting build', [
            'project_id' => $projectId,
            'branch'     => $branch,
            'commit_sha' => $commitSha,
        ]);

        $content = $this->bareRepository->getFileContent($projectId, $commitSha, '.tragwerk/config.xml');

        if ($content === null || $content === '') {
            $this->log($projectId, $branch, 'No .tragwerk/config.xml found — build skipped');

            return;
        }

        try {
            $config = $this->parseConfig($content);
        } catch (RuntimeException $e) {
            $this->log($projectId, $branch, 'Config parse error: ' . $e->getMessage());

            return;
        }

        $outDir = $this->ensureBuildDir($projectId, $branch);

        $projectIdentifier    = ProjectIdentifier::fromString($projectId);
        $domainsByPlaceholder = [];
        foreach ($this->domainRepository->findByEnvironment($projectIdentifier, $branch) as $domain) {
            $domainsByPlaceholder[$domain->placeholder][] = $domain->host;
        }

        $acmeEmail   = $this->ownerEmail($projectIdentifier);
        $messages    = ['Build started for commit ' . $commitSha];
        $userEnvVars = $this->resolveEnvVars($projectIdentifier, $branch);

        try {
            $compose = Yaml::dump(
                $this->composeGenerator->generate($config, $branch, $domainsByPlaceholder, userEnvVars: $userEnvVars),
                10,
                2,
            );
            file_put_contents($outDir . '/docker-compose.yml', $compose);
            $messages[] = 'Generated docker-compose.yml';
        } catch (RuntimeException $e) {
            $messages[] = 'Failed to generate docker-compose.yml: ' . $e->getMessage();
            $this->log($projectId, $branch, implode("\n", $messages));

            return;
        }

        foreach ($config->applications as $app) {
            try {
                $dockerfile = $this->dockerfileGenerator->generate($app);

                file_put_contents($outDir . '/' . $dockerfile->dockerfileName, $dockerfile->dockerfileContent);
                $messages[] = 'Generated ' . $dockerfile->dockerfileName;

                if ($dockerfile->caddyfileName !== null && $dockerfile->caddyfileContent !== null) {
                    file_put_contents($outDir . '/' . $dockerfile->caddyfileName, $dockerfile->caddyfileContent);
                    $messages[] = 'Generated ' . $dockerfile->caddyfileName;
                }

                if ($dockerfile->entrypointName !== null && $dockerfile->entrypointContent !== null) {
                    $path = $outDir . '/' . $dockerfile->entrypointName;
                    file_put_contents($path, $dockerfile->entrypointContent);
                    chmod($path, 0755);
                    $messages[] = 'Generated ' . $dockerfile->entrypointName;
                }
            } catch (RuntimeException $e) {
                $messages[] = 'Failed to generate Dockerfile for ' . $app->name . ': ' . $e->getMessage();
            }
        }

        $this->createBuildZip($outDir);

        $deployJob = new DeployJob(
            id:        DeployJobIdentifier::create(),
            projectId: ProjectIdentifier::fromString($projectId),
            branch:    $branch,
            commitSha: $commitSha,
            status:    DeployJobStatus::Pending,
            output:    '',
            createdAt: TimestampImmutable::now(),
            updatedAt: TimestampImmutable::now(),
        );
        $this->eventDispatcher->dispatch(new DeployJobCreated($deployJob));

        $this->producer->sendMessage(
            new Message\DeployEnvironment($projectId, $branch, $commitSha, $deployJob->id->toString(), $acmeEmail),
        );

        $this->log($projectId, $branch, implode("\n", $messages));

        $this->logger->info('Build completed', [
            'project_id' => $projectId,
            'branch'     => $branch,
        ]);
    }

    private function createBuildZip(string $outDir): void
    {
        $zipPath = $outDir . '/build.zip';
        $zip     = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return;
        }

        foreach (glob($outDir . '/*') ?: [] as $file) {
            if ($file === $zipPath) {
                continue;
            }

            $zip->addFile($file, basename($file));
        }

        $zip->close();
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

    private function ensureBuildDir(string $projectId, string $branch): string
    {
        $base = rtrim($this->projectDataPath, '/') . '/' . $projectId . '/' . $branch;
        if (! is_dir($base)) {
            mkdir($base, 0755, true);
        }

        return $base;
    }

    private function ownerEmail(ProjectIdentifier $projectId): string
    {
        try {
            $project = $this->projectRepository->getById($projectId);
            assert($project instanceof Project);

            $team = $this->teamRepository->getById($project->teamId);
            assert($team instanceof Team);

            $user = $this->userRepository->getById($team->ownerId);
            assert($user instanceof User);

            return $user->email;
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Merges branch-specific vars and inherited ancestor vars into a key→value map.
     * Branch-specific vars override inherited; among inherited, closer ancestor wins.
     *
     * @return array<string, string>
     */
    private function resolveEnvVars(ProjectIdentifier $projectId, string $branch): array
    {
        $ancestors     = $this->ancestorResolver->getAncestors($projectId->toString(), $branch);
        $inheritedVars = $this->envVarRepository->findInheritedFromAncestors($projectId, $ancestors);
        $ownVars       = $this->envVarRepository->findByBranch($projectId, $branch);

        $resolved = [];

        // Inherited vars: iterate from farthest ancestor to closest so closer ancestor wins
        $ancestorOrder   = array_flip($ancestors);
        $sortedInherited = iterator_to_array($inheritedVars, false);
        usort($sortedInherited, static function ($a, $b) use ($ancestorOrder): int {
            return ($ancestorOrder[$b->branch] ?? PHP_INT_MAX) <=> ($ancestorOrder[$a->branch] ?? PHP_INT_MAX);
        });

        foreach ($sortedInherited as $var) {
            $resolved[$var->key] = $var->value;
        }

        // Branch-specific vars always win
        foreach ($ownVars as $var) {
            $resolved[$var->key] = $var->value;
        }

        return $resolved;
    }

    private function log(string $projectId, string $branch, string $message): void
    {
        $this->eventDispatcher->dispatch(new BuildLogCreated(new BuildLog(
            id:        BuildLogIdentifier::create(),
            projectId: ProjectIdentifier::fromString($projectId),
            branch:    $branch,
            type:      BuildLogType::BUILD,
            message:   $message,
            createdAt: TimestampImmutable::now(),
        )));
    }
}
