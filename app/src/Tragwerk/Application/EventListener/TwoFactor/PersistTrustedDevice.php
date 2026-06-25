<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\TwoFactor;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\TrustedDeviceRepository;

final readonly class PersistTrustedDevice
{
    public function __construct(
        private TrustedDeviceRepository $trustedDeviceRepository,
    ) {
    }

    public function __invoke(Event\TrustedDeviceAdded $event): void
    {
        $this->trustedDeviceRepository->create($event->device);
    }
}
