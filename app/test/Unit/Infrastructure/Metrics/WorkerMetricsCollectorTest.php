<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Infrastructure\Metrics;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Infrastructure\Metrics\WorkerMetricsCollector;
use Tragwerk\Infrastructure\Ssh\RemoteShell;

final class WorkerMetricsCollectorTest extends TestCase
{
    private WorkerMetricsCollector $collector;

    protected function setUp(): void
    {
        // parse() never touches the shell, so a real (stateless) RemoteShell is fine here.
        $this->collector = new WorkerMetricsCollector(new RemoteShell());
    }

    #[Test]
    public function sumsMetricsAcrossLabelSeries(): void
    {
        $output = <<<'TXT'
            # HELP frankenphp_total_workers The total number of workers.
            # TYPE frankenphp_total_workers gauge
            frankenphp_total_workers{worker="a"} 4
            frankenphp_total_workers{worker="b"} 2
            frankenphp_busy_workers{worker="a"} 1
            frankenphp_busy_workers{worker="b"} 2
            frankenphp_ready_workers{worker="a"} 4
            frankenphp_worker_queue_depth{worker="a"} 3
            frankenphp_worker_request_count{worker="a"} 150
            frankenphp_worker_crashes{worker="a"} 0
            frankenphp_worker_restarts{worker="a"} 1
            frankenphp_total_threads 8
            TXT;

        $metrics = $this->collector->parse($output);

        self::assertNotNull($metrics);
        self::assertSame(6, $metrics->totalWorkers);   // 4 + 2
        self::assertSame(3, $metrics->busyWorkers);    // 1 + 2
        self::assertSame(4, $metrics->readyWorkers);
        self::assertSame(3, $metrics->queueDepth);
        self::assertSame(150, $metrics->requestCount);
        self::assertSame(0, $metrics->crashes);
        self::assertSame(1, $metrics->restarts);
    }

    #[Test]
    public function returnsNullWhenNoFrankenphpMetrics(): void
    {
        self::assertNull($this->collector->parse(''));
        self::assertNull($this->collector->parse("php: not found\nsome error output"));
    }
}
