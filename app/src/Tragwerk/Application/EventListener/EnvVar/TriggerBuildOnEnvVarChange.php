<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\EnvVar;

use Tragwerk\Application\Queue\Message;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Application\Service\BranchAncestorResolver;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\DeployJobRepository;

final readonly class TriggerBuildOnEnvVarChange
{
    public function __construct(
        private DeployJobRepository $deployJobRepository,
        private Producer $producer,
        private BranchAncestorResolver $ancestorResolver,
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

        $branchesToRebuild = [$branch];

        if ($isInherited) {
            $descendants       = $this->ancestorResolver->getDescendants($projectId->toString(), $branch);
            $branchesToRebuild = [...$branchesToRebuild, ...$descendants];
        }

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
}
