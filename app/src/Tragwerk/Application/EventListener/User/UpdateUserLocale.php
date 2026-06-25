<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\User;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\UserRepository;

final readonly class UpdateUserLocale
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(Event\UserLocaleUpdated $event): void
    {
        $this->userRepository->updateLocale($event->userId, $event->locale);
    }
}
