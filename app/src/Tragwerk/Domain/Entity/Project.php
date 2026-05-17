<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final class Project implements Entity
{
    public function __construct(
        public ProjectIdentifier $id,
        public string $name,
        public UserIdentifier $ownerId,
        public TimestampImmutable $createdAt,
        public UserIdentifier $createdBy,
        public TimestampImmutable $updatedAt,
        public UserIdentifier $updatedBy,
    ) {
    }
}
