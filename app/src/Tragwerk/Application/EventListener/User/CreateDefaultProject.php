<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\User;

use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function _;

final readonly class CreateDefaultProject
{
    public function __construct(
        private ProjectRepository $projectRepository,
    ) {
    }

    public function __invoke(Event\UserRegistered $event): void
    {
        $now     = TimestampImmutable::now();
        $project = new Project(
            ProjectIdentifier::create(),
            _('Default'),
            $event->user->id,
            $now,
            $event->user->id,
            $now,
            $event->user->id,
        );

        $this->projectRepository->create($project);
        $this->projectRepository->assignUsers($project->id, [$event->user->id]);
    }
}
