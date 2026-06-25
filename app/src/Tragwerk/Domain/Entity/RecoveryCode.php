<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use SensitiveParameter;
use Tragwerk\Domain\ValueObject\RecoveryCodeIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final class RecoveryCode implements Entity
{
    public function __construct(
        public RecoveryCodeIdentifier $id,
        public UserIdentifier $userId,
        #[SensitiveParameter]
        public string $codeHash,
        public TimestampImmutable $createdAt,
        public TimestampImmutable|null $usedAt = null,
    ) {
    }

    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }
}
