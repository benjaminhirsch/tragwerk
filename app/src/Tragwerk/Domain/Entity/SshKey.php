<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Domain\ValueObject\SshKeyIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final class SshKey implements Entity
{
    public function __construct(
        public SshKeyIdentifier $id,
        public UserIdentifier $userId,
        public string $name,
        public string $publicKey,
        public TimestampImmutable $createdAt,
    ) {
    }
}
