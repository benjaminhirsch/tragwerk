<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Domain\Enum\BuildLogType;
use Tragwerk\Domain\ValueObject\BuildLogIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

final class BuildLog implements Entity
{
    public function __construct(
        public BuildLogIdentifier $id,
        public ProjectIdentifier $projectId,
        public string $branch,
        public BuildLogType $type,
        public string $message,
        public TimestampImmutable $createdAt,
    ) {
    }
}
