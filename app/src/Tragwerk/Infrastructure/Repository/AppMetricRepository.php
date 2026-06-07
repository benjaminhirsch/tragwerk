<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Override;
use Ramsey\Uuid\Uuid;
use Tragwerk\Domain\Model\EnvironmentMetrics;
use Tragwerk\Domain\Repository\AppMetricRepository as AppMetricRepositoryInterface;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function array_map;
use function round;

/**
 * @phpstan-type AggregatedRow array{
 *     t: string, busy: string|null, total: string|null, ready: string|null, queue: string|null,
 *     req_rate: string, err_pct: string, latency_ms: string
 * }
 */
final readonly class AppMetricRepository implements AppMetricRepositoryInterface
{
    private const string TABLE     = 'app_metrics';
    private const string TS_FORMAT = 'Y-m-d H:i:s.u P';

    public function __construct(private Connection $connection)
    {
    }

    #[Override]
    public function store(
        ProjectIdentifier $projectId,
        string $branch,
        EnvironmentMetrics $metrics,
        TimestampImmutable|null $sampledAt = null,
    ): void {
        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO app_metrics (
                id, project_id, branch, sampled_at,
                total_workers, busy_workers, ready_workers, queue_depth,
                total_threads, busy_threads,
                requests_total, requests_5xx, requests_4xx,
                duration_sum_ms, duration_count, in_flight
            )
            SELECT :id, :project_id, :branch, :sampled_at,
                   :total_workers, :busy_workers, :ready_workers, :queue_depth,
                   :total_threads, :busy_threads,
                   :requests_total, :requests_5xx, :requests_4xx,
                   :duration_sum_ms, :duration_count, :in_flight
            WHERE EXISTS (SELECT 1 FROM projects WHERE id = :project_id)
            SQL,
            [
                'id'              => Uuid::uuid7()->toString(),
                'project_id'      => $projectId->toString(),
                'branch'          => $branch,
                'sampled_at'      => ($sampledAt ?? TimestampImmutable::now())->toString(),
                'total_workers'   => $metrics->totalWorkers,
                'busy_workers'    => $metrics->busyWorkers,
                'ready_workers'   => $metrics->readyWorkers,
                'queue_depth'     => $metrics->queueDepth,
                'total_threads'   => $metrics->totalThreads,
                'busy_threads'    => $metrics->busyThreads,
                'requests_total'  => $metrics->requestsTotal,
                'requests_5xx'    => $metrics->requests5xx,
                'requests_4xx'    => $metrics->requests4xx,
                'duration_sum_ms' => $metrics->durationSumMs,
                'duration_count'  => $metrics->durationCount,
                'in_flight'       => $metrics->inFlight,
            ],
        );
    }

    /**
     * @return list<array{
     *     t: int, busy: float, total: float, ready: float, queue: float,
     *     reqRate: float, errPct: float, latencyMs: float
     * }>
     */
    #[Override]
    public function getAggregated(
        ProjectIdentifier $projectId,
        string $branch,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $intervalSeconds,
    ): array {
        // Gauges → AVG per bucket.
        // Counters use LAG() to compute per-row delta between consecutive samples (handles the case
        // where only one sample lands per bucket, which would give max-min=0). Negative deltas are
        // clamped to 0 to handle counter resets on container restart.
        $sql = <<<'SQL'
            WITH deltas AS (
                SELECT sampled_at,
                       date_bin(make_interval(secs => :interval), sampled_at, TIMESTAMPTZ 'epoch') AS bucket,
                       busy_workers, total_workers, ready_workers, queue_depth,
                       GREATEST(0, requests_total  - LAG(requests_total)  OVER w) AS dreq,
                       GREATEST(0, requests_5xx    - LAG(requests_5xx)    OVER w) AS d5xx,
                       GREATEST(0, duration_sum_ms - LAG(duration_sum_ms) OVER w) AS dsum,
                       GREATEST(0, duration_count  - LAG(duration_count)  OVER w) AS dcnt,
                       extract(epoch FROM (sampled_at - LAG(sampled_at) OVER w)) AS span
                FROM app_metrics
                WHERE project_id = :pid AND branch = :branch
                  AND sampled_at >= :from AND sampled_at <= :to
                WINDOW w AS (ORDER BY sampled_at)
            ),
            b AS (
                SELECT bucket,
                       avg(busy_workers)  AS busy,
                       avg(total_workers) AS total,
                       avg(ready_workers) AS ready,
                       avg(queue_depth)   AS queue,
                       sum(dreq)          AS dreq,
                       sum(d5xx)          AS d5xx,
                       sum(dsum)          AS dsum,
                       sum(dcnt)          AS dcnt,
                       sum(span)          AS span
                FROM deltas
                WHERE span IS NOT NULL AND span > 0
                GROUP BY bucket
            )
            SELECT extract(epoch FROM bucket)::bigint AS t,
                   busy, total, ready, queue,
                   CASE WHEN span > 0 THEN dreq / span ELSE 0 END                    AS req_rate,
                   CASE WHEN dreq > 0 THEN (d5xx::float / dreq) * 100 ELSE 0 END    AS err_pct,
                   CASE WHEN dcnt  > 0 THEN dsum::float / dcnt         ELSE 0 END   AS latency_ms
            FROM b
            ORDER BY bucket
            SQL;

        /** @var list<AggregatedRow> $rows */
        $rows = $this->connection->executeQuery($sql, [
            'interval' => $intervalSeconds,
            'pid'      => $projectId->toString(),
            'branch'   => $branch,
            'from'     => $from->format(self::TS_FORMAT),
            'to'       => $to->format(self::TS_FORMAT),
        ])->fetchAllAssociative();

        return array_map(static fn (array $r): array => [
            't'         => (int) $r['t'],
            'busy'      => round((float) $r['busy'], 2),
            'total'     => round((float) $r['total'], 2),
            'ready'     => round((float) $r['ready'], 2),
            'queue'     => round((float) $r['queue'], 2),
            'reqRate'   => round((float) $r['req_rate'], 3),
            'errPct'    => round((float) $r['err_pct'], 2),
            'latencyMs' => round((float) $r['latency_ms'], 1),
        ], $rows);
    }

    #[Override]
    public function pruneOlderThan(DateTimeImmutable $threshold): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE sampled_at < :threshold',
            ['threshold' => $threshold->format(self::TS_FORMAT)],
        );
    }
}
