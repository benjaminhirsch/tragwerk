<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Project;

use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\SwarmNode;
use Tragwerk\Domain\Enum\SwarmNodeRole;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function assert;
use function trim;

final readonly class UpdateProject
{
    public function __construct(
        private ProjectRepository $projectRepository,
    ) {
    }

    public function __invoke(Event\ProjectUpdated $event): void
    {
        $project = $this->projectRepository->getById($event->projectId);
        assert($project instanceof Project);

        $project->name      = $event->dto->name;
        $project->serverId  = ServerIdentifier::fromString($event->dto->serverId);
        $project->updatedAt = TimestampImmutable::now();
        $project->updatedBy = $event->updatedBy;

        $rid                 = $event->dto->registryId;
        $project->registryId = $rid !== null && trim($rid) !== '' && RegistryIdentifier::isValid($rid)
            ? RegistryIdentifier::fromString($rid)
            : null;

        $project->swarmEnabled = $event->dto->swarmEnabled;

        $this->projectRepository->update($project);

        foreach ($this->projectRepository->getSwarmNodes($event->projectId) as $existing) {
            $this->projectRepository->removeSwarmNode($existing->projectId, $existing->serverId);
        }

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
