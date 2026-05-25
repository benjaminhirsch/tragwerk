<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Project;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\ProjectRepository;

final readonly class DeleteProject
{
    public function __construct(
        private ProjectRepository $projectRepository,
    ) {
    }

    public function __invoke(Event\ProjectDeleted $event): void
    {
        $this->projectRepository->delete($event->projectId);
    }
}
