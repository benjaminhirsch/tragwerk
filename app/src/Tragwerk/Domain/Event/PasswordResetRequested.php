<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Entity\PasswordReset;
use Tragwerk\Domain\Entity\User;

final readonly class PasswordResetRequested
{
    public function __construct(
        public PasswordReset $passwordReset,
        public User $user,
    ) {
    }
}
