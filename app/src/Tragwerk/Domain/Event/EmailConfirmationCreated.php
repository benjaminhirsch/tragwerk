<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Entity\EmailConfirmation;
use Tragwerk\Domain\Entity\User;

final readonly class EmailConfirmationCreated
{
    public function __construct(
        public EmailConfirmation $confirmation,
        public User $user,
    ) {
    }
}
