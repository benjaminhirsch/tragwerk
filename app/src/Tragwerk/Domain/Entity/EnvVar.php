<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Domain\ValueObject\EnvVarIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

final class EnvVar implements Entity
{
    public function __construct(
        public EnvVarIdentifier $id,
        public ProjectIdentifier $projectId,
        public string $branch,
        public string $key,
        public string $value,
        public bool $isSecret,
        public bool $isInherited,
        public TimestampImmutable $createdAt,
        public TimestampImmutable $updatedAt,
    ) {
    }
}
