<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\User;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\UserRepository;

final readonly class UserRegisteredListener
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(Event\UserRegistered $event): void
    {
        $this->userRepository->create($event->registration->createUser());
    }
}
