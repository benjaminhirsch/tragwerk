<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\User;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\UserRepository;

final readonly class PersistLastActiveTeam
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(Event\UserSwitchedTeam $event): void
    {
        $this->userRepository->setLastActiveTeam($event->userId, $event->teamId);
    }
}
