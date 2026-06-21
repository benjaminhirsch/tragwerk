<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use DateTimeImmutable;
use Tragwerk\Domain\Model\ServerMetricSample;
use Tragwerk\Domain\ValueObject\ServerIdentifier;

interface ServerMetricRepository
{
    public function store(ServerMetricSample $sample): void;

    /** @return list<ServerMetricSample> */
    public function getRange(ServerIdentifier $serverId, DateTimeImmutable $from, DateTimeImmutable $to): array;

    /** Most recent sample for the server, or null if none recorded. */
    public function getLatest(ServerIdentifier $serverId): ServerMetricSample|null;

    /** @return int Number of pruned rows */
    public function pruneOlderThan(DateTimeImmutable $threshold): int;
}
