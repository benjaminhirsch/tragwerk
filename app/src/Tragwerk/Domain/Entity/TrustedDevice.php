<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\TrustedDeviceIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final class TrustedDevice implements Entity
{
    public function __construct(
        public TrustedDeviceIdentifier $id,
        public UserIdentifier $userId,
        public string $tokenHash,
        public TimestampImmutable $expiresAt,
        public TimestampImmutable $createdAt,
        public TimestampImmutable|null $lastUsedAt = null,
        public string|null $userAgent = null,
    ) {
    }
}
