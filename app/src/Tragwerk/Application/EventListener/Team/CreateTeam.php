<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Team;

use Psr\EventDispatcher\EventDispatcherInterface;
use Tragwerk\Domain\Enum\TeamRole;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\TeamRepository;

final readonly class CreateTeam
{
    public function __construct(
        private TeamRepository $teamRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(Event\TeamCreated $event): void
    {
        $team = $event->teamCreation->createTeam($event->createdBy);
        $this->teamRepository->create($team);
        $this->teamRepository->assignUsers($team->id, [$event->createdBy], TeamRole::Owner);

        $this->eventDispatcher->dispatch(new Event\TeamMembersInvited(
            $team->id,
            $event->teamCreation->emailsToInvite,
            $event->teamCreation->rolesToInvite,
            $event->createdBy,
        ));
    }
}
