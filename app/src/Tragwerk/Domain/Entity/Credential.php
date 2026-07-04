<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Domain\Enum\CredentialPrivilege;
use Tragwerk\Domain\ValueObject\CredentialIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final class Credential implements Entity
{
    public function __construct(
        public CredentialIdentifier $id,
        public string $name,
        public string $username,
        public CredentialPrivilege $privilege,
        public string|null $privateKey,
        public TeamIdentifier $teamId,
        public TimestampImmutable $createdAt,
        public UserIdentifier $createdBy,
        public TimestampImmutable $updatedAt,
        public UserIdentifier $updatedBy,
    ) {
    }
}
