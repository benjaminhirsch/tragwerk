<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use DateTimeImmutable;
use Tragwerk\Domain\Model\EnvironmentMetrics;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

interface AppMetricRepository
{
    public function store(
        ProjectIdentifier $projectId,
        string $branch,
        EnvironmentMetrics $metrics,
        TimestampImmutable|null $sampledAt = null,
    ): void;

    /**
     * Returns the metrics aggregated into time buckets of $intervalSeconds: gauges averaged,
     * counters turned into per-second rates / average latency from bucket deltas.
     *
     * @return list<array{
     *     t: int, busy: float, total: float, ready: float, queue: float,
     *     reqRate: float, errPct: float, latencyMs: float
     * }>
     */
    public function getAggregated(
        ProjectIdentifier $projectId,
        string $branch,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $intervalSeconds,
    ): array;

    /** @return int Number of pruned rows */
    public function pruneOlderThan(DateTimeImmutable $threshold): int;
}
