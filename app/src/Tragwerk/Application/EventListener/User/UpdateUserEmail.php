<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\User;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\UserRepository;

final readonly class UpdateUserEmail
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(Event\EmailChanged $event): void
    {
        $this->userRepository->updateEmail($event->userId, $event->newEmail);
    }
}
