<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Team;

use Psr\EventDispatcher\EventDispatcherInterface;
use Tragwerk\Domain\Entity\TeamInvitation;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\TeamInvitationRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\TeamInvitationIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function trim;

final readonly class CreateTeam
{
    public function __construct(
        private TeamRepository $teamRepository,
        private UserRepository $userRepository,
        private TeamInvitationRepository $teamInvitationRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(Event\TeamCreated $event): void
    {
        $team = $event->teamCreation->createTeam($event->createdBy);
        $this->teamRepository->create($team);
        $this->teamRepository->assignUsers($team->id, [$event->createdBy]);

        foreach ($event->teamCreation->emailsToInvite as $rawEmail) {
            $email = trim($rawEmail);
            if ($email === '') {
                continue;
            }

            $existingUser = $this->userRepository->searchByEmail($email)->current();
            if ($existingUser instanceof User) {
                $this->teamRepository->assignUsers($team->id, [$existingUser->id]);
                continue;
            }

            $invitation = new TeamInvitation(
                TeamInvitationIdentifier::create(),
                $team->id,
                $email,
                TeamInvitationIdentifier::create()->toString(),
                TimestampImmutable::now(),
                $event->createdBy,
            );

            $this->teamInvitationRepository->create($invitation);
            $this->eventDispatcher->dispatch(new Event\TeamInvitationCreated($invitation));
        }
    }
}
