<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Project;

use Tragwerk\Domain\Entity\SwarmNode;
use Tragwerk\Domain\Enum\SwarmNodeRole;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\ServerIdentifier;

final readonly class CreateProject
{
    public function __construct(
        private ProjectRepository $projectRepository,
    ) {
    }

    public function __invoke(Event\ProjectCreated $event): void
    {
        $project = $event->dto->createProject($event->projectId, $event->teamId, $event->createdBy);
        $this->projectRepository->create($project);

        foreach ($event->swarmNodes as $nodeData) {
            $this->projectRepository->addSwarmNode(new SwarmNode(
                projectId: $event->projectId,
                serverId:  ServerIdentifier::fromString($nodeData['serverId']),
                role:      SwarmNodeRole::from($nodeData['role']),
                isStorage: $nodeData['isStorage'],
            ));
        }
    }
}
