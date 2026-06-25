<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Entity\UserTwoFactor;

final readonly class TwoFactorEnrollmentStarted
{
    public function __construct(
        public UserTwoFactor $twoFactor,
    ) {
    }
}
