<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\TwoFactor;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\RecoveryCodeRepository;
use Tragwerk\Domain\Repository\TrustedDeviceRepository;
use Tragwerk\Domain\Repository\UserTwoFactorRepository;

/**
 * Tears down all two-factor state for a user: secret, recovery codes and
 * trusted devices, and clears the user's two-factor flag.
 */
final readonly class DisableTwoFactor
{
    public function __construct(
        private UserTwoFactorRepository $userTwoFactorRepository,
        private RecoveryCodeRepository $recoveryCodeRepository,
        private TrustedDeviceRepository $trustedDeviceRepository,
    ) {
    }

    public function __invoke(Event\TwoFactorDisabled $event): void
    {
        $this->userTwoFactorRepository->deleteByUserId($event->userId);
        $this->recoveryCodeRepository->deleteByUserId($event->userId);
        $this->trustedDeviceRepository->deleteByUserId($event->userId);
    }
}
