<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Entity\User;

final readonly class UserRegistered
{
    public function __construct(
        public User $user,
        // The CLI creates already-confirmed users (no mail round-trip on a box
        // that may have no SMTP at all), so it opts out of the confirmation.
        public bool $requiresEmailConfirmation = true,
    ) {
    }
}
