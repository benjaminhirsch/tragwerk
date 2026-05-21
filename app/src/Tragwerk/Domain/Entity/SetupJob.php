<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Domain\Enum\SetupJobStatus;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\SetupJobIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

final class SetupJob implements Entity
{
    public function __construct(
        public SetupJobIdentifier $id,
        public ServerIdentifier $serverId,
        public SetupJobStatus $status,
        public string $output,
        public TimestampImmutable $createdAt,
        public TimestampImmutable $updatedAt,
    ) {
    }
}
