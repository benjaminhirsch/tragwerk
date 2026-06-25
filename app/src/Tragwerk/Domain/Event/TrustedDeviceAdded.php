<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Entity\TrustedDevice;

final readonly class TrustedDeviceAdded
{
    public function __construct(
        public TrustedDevice $device,
    ) {
    }
}
