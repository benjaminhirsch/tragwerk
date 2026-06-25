<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\TwoFactor;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\UserTwoFactorRepository;

final readonly class ConfirmTwoFactor
{
    public function __construct(
        private UserTwoFactorRepository $userTwoFactorRepository,
    ) {
    }

    public function __invoke(Event\TwoFactorEnabled $event): void
    {
        $this->userTwoFactorRepository->confirm($event->userId);
    }
}
