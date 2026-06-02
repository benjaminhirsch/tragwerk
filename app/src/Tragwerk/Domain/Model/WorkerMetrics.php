<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

/**
 * Aggregated FrankenPHP worker metrics for one environment (summed across its app containers).
 */
final readonly class WorkerMetrics
{
    public function __construct(
        public int $totalWorkers,
        public int $busyWorkers,
        public int $readyWorkers,
        public int $queueDepth,
        public int $requestCount,
        public int $crashes,
        public int $restarts,
    ) {
    }
}
