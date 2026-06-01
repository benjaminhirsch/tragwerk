<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

final readonly class ServerMetricSample
{
    public function __construct(
        public ServerIdentifier $serverId,
        public TimestampImmutable $sampledAt,
        public float $cpuPercent,
        public int $memUsedBytes,
        public int $memTotalBytes,
        public int $diskUsedBytes,
        public int $diskTotalBytes,
        public float $load1,
    ) {
    }
}
