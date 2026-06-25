<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\TwoFactor;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\UserTwoFactorRepository;

final readonly class PersistTwoFactorEnrollment
{
    public function __construct(
        private UserTwoFactorRepository $userTwoFactorRepository,
    ) {
    }

    public function __invoke(Event\TwoFactorEnrollmentStarted $event): void
    {
        // Replace any prior (unconfirmed) enrollment so a fresh secret takes over.
        $this->userTwoFactorRepository->deleteByUserId($event->twoFactor->userId);
        $this->userTwoFactorRepository->create($event->twoFactor);
    }
}
