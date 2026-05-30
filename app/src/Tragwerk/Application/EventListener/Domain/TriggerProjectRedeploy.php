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
        $branch    = $event instanceof Event\DomainAdded ? $event->domain->branch : $event->branch;

        $latestJob = $this->deployJobRepository->getLatestByProjectAndBranch($projectId, $branch);
        if ($latestJob === null) {
            return;
        }

        $this->producer->sendMessage(new Message\BuildEnvironment(
            $projectId->toString(),
            $branch,
            $latestJob->commitSha,
        ));
    }
}
