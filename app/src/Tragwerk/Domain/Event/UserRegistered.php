<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Application\Dto\UserRegistration;

final readonly class UserRegistered
{
    public function __construct(
        public UserRegistration $registration,
    ) {
    }
}
