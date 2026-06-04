<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\User;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\PasswordResetRepository;

final readonly class CreatePasswordReset
{
    public function __construct(
        private PasswordResetRepository $passwordResetRepository,
    ) {
    }

    public function __invoke(Event\PasswordResetRequested $event): void
    {
        $this->passwordResetRepository->create($event->passwordReset);
    }
}
