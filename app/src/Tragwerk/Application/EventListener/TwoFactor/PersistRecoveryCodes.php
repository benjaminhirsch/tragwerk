<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\TwoFactor;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\RecoveryCodeRepository;

final readonly class PersistRecoveryCodes
{
    public function __construct(
        private RecoveryCodeRepository $recoveryCodeRepository,
    ) {
    }

    public function __invoke(Event\RecoveryCodesGenerated $event): void
    {
        // Regenerating invalidates the previous set.
        $this->recoveryCodeRepository->deleteByUserId($event->userId);

        foreach ($event->codes as $code) {
            $this->recoveryCodeRepository->create($code);
        }
    }
}
