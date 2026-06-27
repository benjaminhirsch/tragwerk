<?php

declare(strict_types=1);

namespace Tragwerk\Application\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use Tragwerk\Application\Queue\Message\BuildEnvironment;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Domain\Config\ConfigValidator;
use Tragwerk\Domain\Entity\BuildLog;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Enum\BuildLogType;
use Tragwerk\Domain\Event\BuildLogCreated;
use Tragwerk\Domain\Repository\DomainRepository;
use Tragwerk\Domain\Service\DomainResolver;
use Tragwerk\Domain\ValueObject\BuildLogIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Infrastructure\Git\BareRepository;

use function implode;

final readonly class BuildDispatcher
{
    public function __construct(
        private BareRepository $bareRepository,
        private ConfigValidator $configValidator,
        private EventDispatcherInterface $eventDispatcher,
        private DomainRepository $domainRepository,
        private DomainResolver $domainResolver,
        private Producer $producer,
    ) {
    }

    public function dispatch(
        Project $project,
        string $branch,
        string $commitSha,
        BuildLogType $logType = BuildLogType::PUSH,
    ): void {
        [$message, $configValid] = $this->validateConfig($project->id->toString(), $commitSha);

        $this->eventDispatcher->dispatch(new BuildLogCreated(new BuildLog(
            id:        BuildLogIdentifier::create(),
            projectId: $project->id,
            branch:    $branch,
            type:      $logType,
            message:   $message,
            createdAt: TimestampImmutable::now(),
        )));

        $isMain    = $branch === 'main' || $branch === 'master';
        $hasDomain = $isMain || $this->domainResolver->resolveForEnvironment(
            $this->domainRepository->findByProject($project->id),
            $branch,
        ) !== [];

        if (! $configValid || ! $hasDomain) {
            return;
        }

        $this->producer->sendMessage(new BuildEnvironment(
            projectId: $project->id->toString(),
            branch:    $branch,
            commitSha: $commitSha,
        ));
    }

    /** @return array{string, bool} */
    private function validateConfig(string $projectId, string $commitSha): array
    {
        $content = $this->bareRepository->getFileContent($projectId, $commitSha, '.tragwerk/config.xml');

        if ($content === null || $content === '') {
            return ['No .tragwerk/config.xml found — skipping validation', false];
        }

        $errors = $this->configValidator->validate($content);

        if ($errors === []) {
            return ['Configuration validated successfully', true];
        }

        return ['Configuration is invalid:' . "\n" . implode("\n", $errors), false];
    }
}
