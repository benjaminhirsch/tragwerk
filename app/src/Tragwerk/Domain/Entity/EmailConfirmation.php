<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Domain\ValueObject\EmailConfirmationIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final class EmailConfirmation implements Entity
{
    public function __construct(
        public EmailConfirmationIdentifier $id,
        public UserIdentifier $userId,
        public string $token,
        public TimestampImmutable $expiresAt,
        public TimestampImmutable $createdAt,
    ) {
    }
}
