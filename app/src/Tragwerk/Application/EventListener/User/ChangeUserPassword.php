<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\User;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\UserRepository;

final readonly class ChangeUserPassword
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(Event\UserPasswordChanged $event): void
    {
        $this->userRepository->updatePassword($event->userId, $event->passwordHash);
    }
}
