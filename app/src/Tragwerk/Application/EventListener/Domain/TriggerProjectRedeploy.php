<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Domain;

use Tragwerk\Application\Queue\Message;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\DeployJobRepository;

final readonly class TriggerProjectRedeploy
{
    public function __construct(
        private DeployJobRepository $deployJobRepository,
        private Producer $producer,
    ) {
    }

    public function __invoke(Event\DomainAdded|Event\DomainDeleted $event): void
    {
        $projectId = $event->projectId;

        // Domains are project-wide now — a change can affect any environment, so rebuild every
        // environment that already has a deployment.
        foreach ($this->deployJobRepository->getDeployedBranches($projectId) as $branch) {
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
