<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Team;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\TeamRepository;

final readonly class DeleteTeam
{
    public function __construct(
        private TeamRepository $teamRepository,
    ) {
    }

    public function __invoke(Event\TeamDeleted $event): void
    {
        $this->teamRepository->delete($event->teamId);
    }
}
