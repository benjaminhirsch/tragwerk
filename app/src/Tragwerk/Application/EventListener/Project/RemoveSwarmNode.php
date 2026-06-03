<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Project;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\ProjectRepository;

final readonly class RemoveSwarmNode
{
    public function __construct(
        private ProjectRepository $projectRepository,
    ) {
    }

    public function __invoke(Event\SwarmNodeRemoved $event): void
    {
        $this->projectRepository->removeSwarmNode($event->projectId, $event->serverId);
    }
}
