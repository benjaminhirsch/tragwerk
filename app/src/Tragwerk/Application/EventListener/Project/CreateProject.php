<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Project;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\ProjectRepository;

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
    }
}
