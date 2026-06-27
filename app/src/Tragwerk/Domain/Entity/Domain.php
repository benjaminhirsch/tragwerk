<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Domain\ValueObject\DomainIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

final class Domain implements Entity
{
    public function __construct(
        public DomainIdentifier $id,
        public ProjectIdentifier $projectId,
        public string $host,
        public bool $isPrimary,
        public TimestampImmutable $createdAt,
        public string $placeholder = 'default',
        public bool $isWildcard = false,
    ) {
    }
}
