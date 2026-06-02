<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Infrastructure\Metrics;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Infrastructure\Metrics\EnvironmentMetricsCollector;
use Tragwerk\Infrastructure\Ssh\RemoteShell;

final class EnvironmentMetricsCollectorTest extends TestCase
{
    private EnvironmentMetricsCollector $collector;

    protected function setUp(): void
    {
        // parse*() never touch the shell, so a real (stateless) RemoteShell is fine here.
        $this->collector = new EnvironmentMetricsCollector(new RemoteShell());
    }

    #[Test]
    public function sumsWorkerThreadAndHttpMetrics(): void
    {
        $output = <<<'TXT'
            # HELP frankenphp_total_workers The total number of workers.
            frankenphp_total_workers{worker="a"} 4
            frankenphp_total_workers{worker="b"} 2
            frankenphp_busy_workers{worker="a"} 1
            frankenphp_busy_workers{worker="b"} 2
            frankenphp_ready_workers{worker="a"} 4
            frankenphp_worker_queue_depth{worker="a"} 3
            frankenphp_total_threads 8
            frankenphp_busy_threads 5
            frankenphp_worker_request_count{worker="a"} 150
            frankenphp_worker_crashes{worker="a"} 0
            frankenphp_worker_restarts{worker="a"} 1
            caddy_http_requests_total{code="200",handler="file_server"} 100
            caddy_http_requests_total{code="200",handler="php"} 40
            caddy_http_requests_total{code="404"} 7
            caddy_http_requests_total{code="503"} 3
            caddy_http_request_duration_seconds_sum{handler="php"} 1.5
            caddy_http_request_duration_seconds_count{handler="php"} 3
            caddy_http_requests_in_flight{handler="php"} 2
            TXT;

        $m = $this->collector->parse($output);

        self::assertNotNull($m);
        self::assertSame(6, $m->totalWorkers);   // 4 + 2
        self::assertSame(3, $m->busyWorkers);    // 1 + 2
        self::assertSame(4, $m->readyWorkers);
        self::assertSame(3, $m->queueDepth);
        self::assertSame(8, $m->totalThreads);
        self::assertSame(5, $m->busyThreads);
        self::assertSame(150, $m->requestCount);
        self::assertSame(1, $m->restarts);
        self::assertSame(150, $m->requestsTotal); // 100 + 40 + 7 + 3
        self::assertSame(3, $m->requests5xx);     // code 503
        self::assertSame(7, $m->requests4xx);     // code 404
        self::assertSame(1500, $m->durationSumMs); // 1.5s → 1500ms
        self::assertSame(3, $m->durationCount);
        self::assertSame(2, $m->inFlight);
    }

    #[Test]
    public function returnsNullWhenNeitherFrankenphpNorCaddyMetricsPresent(): void
    {
        self::assertNull($this->collector->parse(''));
        self::assertNull($this->collector->parse("php: not found\nsome other output"));
    }

    #[Test]
    public function returnsMetricsWithZeroWorkersWhenOnlyCaddyPresent(): void
    {
        $output = <<<'TXT'
            caddy_http_requests_total{code="200"} 50
            caddy_http_request_duration_seconds_sum{} 0.5
            caddy_http_request_duration_seconds_count{} 5
            caddy_http_requests_in_flight{} 1
            TXT;

        $m = $this->collector->parse($output);

        self::assertNotNull($m);
        self::assertSame(0, $m->totalWorkers);
        self::assertSame(50, $m->requestsTotal);
        self::assertSame(500, $m->durationSumMs); // 0.5s → 500ms
        self::assertSame(1, $m->inFlight);
    }

    #[Test]
    public function parseServerGroupsBlocksPerEnvironment(): void
    {
        $output = <<<'TXT'
            ===ENV /home/deploy/tragwerk/proj-1/main
            frankenphp_total_workers{worker="a"} 4
            caddy_http_requests_total{code="200"} 100
            caddy_http_requests_total{code="500"} 5
            ===ENV /home/deploy/tragwerk/proj-2/feature/login
            frankenphp_total_workers{worker="a"} 2
            TXT;

        $envs = $this->collector->parseServer($output);

        self::assertCount(2, $envs);

        self::assertSame('proj-1', $envs[0]['projectId']);
        self::assertSame('main', $envs[0]['branch']);
        self::assertSame(4, $envs[0]['metrics']->totalWorkers);
        self::assertSame(105, $envs[0]['metrics']->requestsTotal);
        self::assertSame(5, $envs[0]['metrics']->requests5xx);

        // Branch names containing slashes are preserved.
        self::assertSame('proj-2', $envs[1]['projectId']);
        self::assertSame('feature/login', $envs[1]['branch']);
        self::assertSame(2, $envs[1]['metrics']->totalWorkers);
    }

    #[Test]
    public function parseServerSkipsEnvWithoutFrankenphpMetrics(): void
    {
        $output = <<<'TXT'
            ===ENV /home/deploy/tragwerk/proj-1/main
            php: not found
            TXT;

        self::assertSame([], $this->collector->parseServer($output));
    }
}
