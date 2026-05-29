<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Domain\Enum\DeployJobStatus;
use Tragwerk\Domain\ValueObject\DeployJobIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

final class DeployJob implements Entity
{
    public function __construct(
        public DeployJobIdentifier $id,
        public ProjectIdentifier $projectId,
        public string $branch,
        public string $commitSha,
        public DeployJobStatus $status,
        public string $output,
        public TimestampImmutable $createdAt,
        public TimestampImmutable $updatedAt,
    ) {
    }
}
