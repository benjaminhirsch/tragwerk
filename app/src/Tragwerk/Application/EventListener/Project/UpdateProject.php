<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Project;

use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function assert;

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

        $this->projectRepository->update($project);
    }
}
