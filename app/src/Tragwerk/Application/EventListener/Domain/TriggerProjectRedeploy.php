<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Domain;

use Tragwerk\Application\Queue\Message;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\Repository\EnvironmentRepository;

final readonly class TriggerProjectRedeploy
{
    public function __construct(
        private EnvironmentRepository $environmentRepository,
        private DeployJobRepository $deployJobRepository,
        private Producer $producer,
    ) {
    }

    public function __invoke(Event\DomainAdded|Event\DomainDeleted $event): void
    {
        $projectId = $event->projectId;
        $branches  = $this->environmentRepository->getActiveBranches($projectId);

        foreach ($branches as $branch) {
            $latestJob = $this->deployJobRepository->getLatestByProjectAndBranch($projectId, $branch);
            if ($latestJob === null) {
                continue;
            }

            $this->producer->sendMessage(new Message\BuildEnvironment(
                $projectId->toString(),
                $branch,
                $latestJob->commitSha,
            ));
        }
    }
}
