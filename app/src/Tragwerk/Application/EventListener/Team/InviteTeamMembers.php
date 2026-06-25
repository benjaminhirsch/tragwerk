<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Team;

use Psr\EventDispatcher\EventDispatcherInterface;
use Tragwerk\Application\Dto\Team\TeamRoleSelection;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\TeamInvitation;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\TeamInvitationRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\TeamInvitationIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function assert;
use function trim;

/**
 * Single source of truth for adding members to an existing team: existing users are
 * assigned directly, unknown emails become pending invitations. Triggered by team
 * creation, full team updates, and the dedicated invite action.
 */
final readonly class InviteTeamMembers
{
    public function __construct(
        private TeamRepository $teamRepository,
        private UserRepository $userRepository,
        private TeamInvitationRepository $teamInvitationRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(Event\TeamMembersInvited $event): void
    {
        $team = $this->teamRepository->getById($event->teamId);
        assert($team instanceof Team);

        foreach ($event->emails as $i => $rawEmail) {
            $email = trim($rawEmail);
            if ($email === '') {
                continue;
            }

            $role = TeamRoleSelection::fromArray($event->roles, (int) $i);

            $existingUser = $this->userRepository->searchByEmail($email)->current();
            if ($existingUser instanceof User) {
                $this->teamRepository->assignUsers($team->id, [$existingUser->id], $role);
                $this->eventDispatcher->dispatch(new Event\TeamMemberAdded($team, $existingUser));
                continue;
            }

            $invitation = new TeamInvitation(
                TeamInvitationIdentifier::create(),
                $team->id,
                $email,
                TeamInvitationIdentifier::create()->toString(),
                TimestampImmutable::now(),
                $event->invitedBy,
                $role,
            );

            $this->teamInvitationRepository->create($invitation);
            $this->eventDispatcher->dispatch(new Event\TeamInvitationCreated($invitation));
        }
    }
}
