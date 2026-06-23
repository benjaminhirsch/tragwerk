<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Event;

use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tragwerk\Domain\Event\AppMetricsSampled;
use Tragwerk\Domain\Model\EnvironmentMetrics;
use TragwerkTest\Integration\Support\EnvironmentScopedTestCase;

use function assert;
use function is_numeric;

final class AppMetricsSampledTest extends EnvironmentScopedTestCase
{
    #[Test]
    public function persistsMetricsForValidProject(): void
    {
        $this->dispatcher()->dispatch(new AppMetricsSampled(
            $this->project->id->toString(),
            $this->branch,
            $this->metrics(),
        ));

        self::assertSame(1, $this->appMetricCount());
    }

    #[Test]
    public function ignoresInvalidProjectId(): void
    {
        $this->dispatcher()->dispatch(new AppMetricsSampled(
            'not-a-valid-uuid',
            $this->branch,
            $this->metrics(),
        ));

        self::assertSame(0, $this->appMetricCount());
    }

    private function metrics(): EnvironmentMetrics
    {
        return new EnvironmentMetrics(
            totalWorkers: 4,
            busyWorkers: 2,
            readyWorkers: 2,
            queueDepth: 0,
            totalThreads: 8,
            busyThreads: 3,
            requestCount: 100,
            crashes: 0,
            restarts: 1,
            requestsTotal: 100,
            requests5xx: 1,
            requests4xx: 2,
            durationSumMs: 5_000,
            durationCount: 100,
            inFlight: 1,
        );
    }

    private function appMetricCount(): int
    {
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM app_metrics');
        assert(is_numeric($count));

        return (int) $count;
    }

    private function dispatcher(): EventDispatcherInterface
    {
        $dispatcher = $this->container->get(EventDispatcherInterface::class);
        assert($dispatcher instanceof EventDispatcherInterface);

        return $dispatcher;
    }
}
