<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Team;

use Psr\EventDispatcher\EventDispatcherInterface;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;
use function is_string;

final readonly class UpdateTeam
{
    public function __construct(
        private TeamRepository $teamRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(Event\TeamUpdated $event): void
    {
        $team = $this->teamRepository->getById($event->teamId);
        assert($team instanceof Team);

        $now             = TimestampImmutable::now();
        $team->name      = $event->teamUpdate->name;
        $team->updatedAt = $now;
        $team->updatedBy = $event->updatedBy;

        $this->teamRepository->update($team);

        foreach ($event->teamUpdate->usersToRemove as $rawId) {
            if (! is_string($rawId) || ! UserIdentifier::isValid($rawId)) {
                continue;
            }

            $removeId = UserIdentifier::fromString($rawId);
            if ($removeId->toString() === $team->ownerId->toString()) {
                continue;
            }

            $this->teamRepository->removeUser($team->id, $removeId);
        }

        $this->eventDispatcher->dispatch(new Event\TeamMembersInvited(
            $team->id,
            $event->teamUpdate->emailsToInvite,
            $event->teamUpdate->rolesToInvite,
            $event->updatedBy,
        ));
    }
}
