<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Domain\ValueObject\PasswordResetIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final class PasswordReset implements Entity
{
    public function __construct(
        public PasswordResetIdentifier $id,
        public UserIdentifier $userId,
        public string $token,
        public TimestampImmutable $expiresAt,
        public TimestampImmutable $createdAt,
        public TimestampImmutable|null $usedAt = null,
    ) {
    }
}
