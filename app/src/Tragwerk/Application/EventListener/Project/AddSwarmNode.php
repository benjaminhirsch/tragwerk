<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Project;

use Tragwerk\Domain\Entity\SwarmNode;
use Tragwerk\Domain\Enum\SwarmNodeRole;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\ProjectRepository;

final readonly class AddSwarmNode
{
    public function __construct(
        private ProjectRepository $projectRepository,
    ) {
    }

    public function __invoke(Event\SwarmNodeAdded $event): void
    {
        $this->projectRepository->addSwarmNode(new SwarmNode(
            projectId: $event->projectId,
            serverId:  $event->serverId,
            role:      SwarmNodeRole::Worker,
            isStorage: false,
        ));
    }
}
