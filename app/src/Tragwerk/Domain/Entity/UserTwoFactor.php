<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use SensitiveParameter;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use Tragwerk\Domain\ValueObject\UserTwoFactorIdentifier;

final class UserTwoFactor implements Entity
{
    public function __construct(
        public UserTwoFactorIdentifier $id,
        public UserIdentifier $userId,
        #[SensitiveParameter]
        public string $secret,
        public TimestampImmutable $createdAt,
        public TimestampImmutable $updatedAt,
        public TimestampImmutable|null $confirmedAt = null,
    ) {
    }

    public function isConfirmed(): bool
    {
        return $this->confirmedAt !== null;
    }
}
