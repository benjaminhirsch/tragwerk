<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\User;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\UserRepository;

final readonly class UpdateUserProfile
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(Event\UserProfileUpdated $event): void
    {
        $this->userRepository->updateProfile($event->userId, $event->firstname, $event->lastname);
    }
}
