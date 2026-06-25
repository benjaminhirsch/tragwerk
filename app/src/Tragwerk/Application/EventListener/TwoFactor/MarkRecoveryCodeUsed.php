<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\TwoFactor;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\RecoveryCodeRepository;

final readonly class MarkRecoveryCodeUsed
{
    public function __construct(
        private RecoveryCodeRepository $recoveryCodeRepository,
    ) {
    }

    public function __invoke(Event\RecoveryCodeConsumed $event): void
    {
        $this->recoveryCodeRepository->markUsed($event->id);
    }
}
