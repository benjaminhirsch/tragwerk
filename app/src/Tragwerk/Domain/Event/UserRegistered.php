<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Entity\User;

final readonly class UserRegistered
{
    public function __construct(
        public User $user,
    ) {
    }
}
