<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final class Registry implements Entity
{
    public function __construct(
        public RegistryIdentifier $id,
        public string $name,
        public string $url,
        public string $repository,
        public string $username,
        public string $password,
        public bool $pruningEnabled,
        public int $keepTags,
        public TeamIdentifier $teamId,
        public TimestampImmutable $createdAt,
        public UserIdentifier $createdBy,
        public TimestampImmutable $updatedAt,
        public UserIdentifier $updatedBy,
    ) {
    }
}
