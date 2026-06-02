<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Domain\Repository;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Model\EnvironmentMetrics;
use Tragwerk\Domain\Repository\AppMetricRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\IntegrationTestCase;

use function assert;

final class AppMetricRepositoryTest extends IntegrationTestCase
{
    private AppMetricRepository $repository;
    private ProjectIdentifier $projectId;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->container->get(AppMetricRepository::class);
        assert($repository instanceof AppMetricRepository);
        $this->repository = $repository;
        $this->projectId  = $this->seedProject();
    }

    #[Test]
    public function aggregatesGaugesAsAveragesAndCountersAsRates(): void
    {
        $base = new DateTimeImmutable('2026-06-01 12:00:00+00');

        // requests 0 → 30 → 60 over 60s; duration sum 0 → 1000ms over 0 → 10 requests.
        $this->store($base, requestsTotal: 0, durationSumMs: 0, durationCount: 0);
        $this->store($base->modify('+30 seconds'), requestsTotal: 30, durationSumMs: 500, durationCount: 5);
        $this->store($base->modify('+60 seconds'), requestsTotal: 60, durationSumMs: 1000, durationCount: 10);

        $rows = $this->repository->getAggregated(
            $this->projectId,
            'main',
            $base->modify('-1 minute'),
            $base->modify('+2 minutes'),
            300,
        );

        self::assertCount(1, $rows);
        self::assertEqualsWithDelta(2.0, $rows[0]['busy'], 0.01);
        self::assertEqualsWithDelta(4.0, $rows[0]['total'], 0.01);
        self::assertEqualsWithDelta(1.0, $rows[0]['reqRate'], 0.01);    // 60 requests / 60s
        self::assertEqualsWithDelta(0.0, $rows[0]['errPct'], 0.01);
        self::assertEqualsWithDelta(100.0, $rows[0]['latencyMs'], 0.01); // 1000ms / 10 requests
    }

    #[Test]
    public function errorPercentReflects5xxShareOfRequests(): void
    {
        $base = new DateTimeImmutable('2026-06-01 12:00:00+00');

        $this->store($base, requestsTotal: 0);
        $this->store($base->modify('+60 seconds'), requestsTotal: 100, requests5xx: 10);

        $rows = $this->repository->getAggregated(
            $this->projectId,
            'main',
            $base->modify('-1 minute'),
            $base->modify('+2 minutes'),
            300,
        );

        self::assertCount(1, $rows);
        self::assertEqualsWithDelta(10.0, $rows[0]['errPct'], 0.01); // 10 of 100 → 10%
    }

    #[Test]
    public function pruneOlderThanDeletesOnlyOldSamples(): void
    {
        $now = new DateTimeImmutable();
        $this->store($now->modify('-40 days'), requestsTotal: 1);
        $this->store($now, requestsTotal: 1);

        $deleted = $this->repository->pruneOlderThan($now->modify('-30 days'));

        self::assertSame(1, $deleted);
    }

    private function store(
        DateTimeImmutable $when,
        int $requestsTotal = 0,
        int $durationSumMs = 0,
        int $durationCount = 0,
        int $requests5xx = 0,
    ): void {
        $metrics = new EnvironmentMetrics(
            totalWorkers:  4,
            busyWorkers:   2,
            readyWorkers:  4,
            queueDepth:    1,
            totalThreads:  8,
            busyThreads:   3,
            requestCount:  0,
            crashes:       0,
            restarts:      0,
            requestsTotal: $requestsTotal,
            requests5xx:   $requests5xx,
            requests4xx:   0,
            durationSumMs: $durationSumMs,
            durationCount: $durationCount,
            inFlight:      0,
        );

        $this->repository->store($this->projectId, 'main', $metrics, TimestampImmutable::fromDateTime($when));
    }

    private function seedProject(): ProjectIdentifier
    {
        $now     = TimestampImmutable::now();
        $project = new Project(
            ProjectIdentifier::create(),
            'Metrics App',
            ServerIdentifier::create(),
            TeamIdentifier::create(),
            $now,
            UserIdentifier::create(),
            $now,
            UserIdentifier::create(),
        );

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);
        $repository->create($project);

        return $project->id;
    }
}
