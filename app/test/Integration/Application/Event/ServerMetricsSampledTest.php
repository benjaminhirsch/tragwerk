<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Event;

use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tragwerk\Domain\Event\ServerMetricsSampled;
use Tragwerk\Domain\Model\ServerMetricSample;
use Tragwerk\Domain\Repository\ServerMetricRepository;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use TragwerkTest\Integration\Support\EnvironmentScopedTestCase;

use function assert;

final class ServerMetricsSampledTest extends EnvironmentScopedTestCase
{
    #[Test]
    public function persistsSampleForServer(): void
    {
        $sample = new ServerMetricSample(
            serverId: $this->server->id,
            sampledAt: TimestampImmutable::now(),
            cpuPercent: 42.5,
            memUsedBytes: 1_000,
            memTotalBytes: 4_000,
            diskUsedBytes: 5_000,
            diskTotalBytes: 20_000,
            load1: 0.75,
        );

        $this->dispatcher()->dispatch(new ServerMetricsSampled($sample));

        $repository = $this->container->get(ServerMetricRepository::class);
        assert($repository instanceof ServerMetricRepository);
        $latest = $repository->getLatest($this->server->id);

        self::assertNotNull($latest);
        self::assertEqualsWithDelta(42.5, $latest->cpuPercent, 0.01);
        self::assertSame(1_000, $latest->memUsedBytes);
    }

    private function dispatcher(): EventDispatcherInterface
    {
        $dispatcher = $this->container->get(EventDispatcherInterface::class);
        assert($dispatcher instanceof EventDispatcherInterface);

        return $dispatcher;
    }
}
