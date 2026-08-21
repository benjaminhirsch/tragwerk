<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\EnvVar;

use Throwable;
use Tragwerk\Application\Queue\Message;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Application\Service\BranchAncestorResolver;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Infrastructure\Git\BareRepository;

use function array_values;

final readonly class TriggerBuildOnEnvVarChange
{
    public function __construct(
        private DeployJobRepository $deployJobRepository,
        private Producer $producer,
        private BranchAncestorResolver $ancestorResolver,
        private BareRepository $bareRepository,
    ) {
    }

    public function __invoke(Event\EnvVarCreated|Event\EnvVarUpdated|Event\EnvVarDeleted $event): void
    {
        if ($event instanceof Event\EnvVarDeleted) {
            $projectId   = $event->projectId;
            $branch      = $event->branch;
            $isInherited = $event->wasInherited;
        } else {
            $projectId   = $event->var->projectId;
            $branch      = $event->var->branch;
            $isInherited = $event->var->isInherited;
        }

        $branchesToRebuild = $this->branchesToRebuild($projectId, $branch, $isInherited);

        foreach ($branchesToRebuild as $targetBranch) {
            $latestJob = $this->deployJobRepository->getLatestByProjectAndBranch($projectId, $targetBranch);
            if ($latestJob === null) {
                continue;
            }

            $this->producer->sendMessage(new Message\BuildEnvironment(
                $projectId->toString(),
                $targetBranch,
                $latestJob->commitSha,
            ));
        }
    }

    /** @return list<string> */
    private function branchesToRebuild(ProjectIdentifier $projectId, string|null $branch, bool $isInherited): array
    {
        // A project-scoped var (NULL branch) feeds every environment, so every branch is affected.
        if ($branch === null) {
            try {
                return array_values($this->bareRepository->getBranches($projectId->toString()));
            } catch (Throwable) {
                // A missing or unreadable repository simply has nothing to rebuild.
                return [];
            }
        }

        if (! $isInherited) {
            return [$branch];
        }

        return [$branch, ...$this->ancestorResolver->getDescendants($projectId->toString(), $branch)];
    }
}
