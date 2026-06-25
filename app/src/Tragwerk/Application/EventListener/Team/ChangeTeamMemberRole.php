<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Team;

use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Enum\TeamRole;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function assert;

final readonly class ChangeTeamMemberRole
{
    public function __construct(
        private TeamRepository $teamRepository,
    ) {
    }

    public function __invoke(Event\TeamMemberRoleChanged $event): void
    {
        if ($event->role === TeamRole::Owner) {
            $this->transferOwnership($event);

            return;
        }

        $this->teamRepository->updateRole($event->teamId, $event->userId, $event->role);
    }

    private function transferOwnership(Event\TeamMemberRoleChanged $event): void
    {
        $team = $this->teamRepository->getById($event->teamId);
        assert($team instanceof Team);

        // Already the owner — nothing to do.
        if ($team->ownerId->toString() === $event->userId->toString()) {
            return;
        }

        // Single-owner invariant: demote the previous owner to admin, promote the new one,
        // and move Team.ownerId so it stays the source of truth.
        $this->teamRepository->updateRole($event->teamId, $team->ownerId, TeamRole::Admin);
        $this->teamRepository->updateRole($event->teamId, $event->userId, TeamRole::Owner);

        $team->ownerId   = $event->userId;
        $team->updatedAt = TimestampImmutable::now();
        $team->updatedBy = $event->changedBy;
        $this->teamRepository->update($team);
    }
}
