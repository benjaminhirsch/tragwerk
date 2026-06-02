<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

/**
 * Aggregated FrankenPHP + Caddy HTTP metrics for one environment, summed across its app containers.
 *
 * Gauges (workers, threads, in-flight) are point-in-time values. Counters (requests*, duration*)
 * are cumulative since container start; rates and average latency are derived from deltas between
 * samples at query time.
 */
final readonly class EnvironmentMetrics
{
    public function __construct(
        // Worker gauges
        public int $totalWorkers,
        public int $busyWorkers,
        public int $readyWorkers,
        public int $queueDepth,
        // Thread gauges
        public int $totalThreads,
        public int $busyThreads,
        // Worker counters (operational, shown live)
        public int $requestCount,
        public int $crashes,
        public int $restarts,
        // HTTP counters (cumulative)
        public int $requestsTotal,
        public int $requests5xx,
        public int $requests4xx,
        public int $durationSumMs,
        public int $durationCount,
        // HTTP gauge
        public int $inFlight,
    ) {
    }
}
