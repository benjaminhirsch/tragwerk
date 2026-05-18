<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\User;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\UserRepository;

final readonly class PersistLastActiveProject
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(Event\UserSwitchedProject $event): void
    {
        $this->userRepository->setLastActiveProject($event->userId, $event->projectId);
    }
}
