<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Handler\Project;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Handler\Project\EnvironmentMetricsDataHandler;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\AppMetricRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;
use function is_array;
use function json_decode;

#[AllowMockObjectsWithoutExpectations]
final class EnvironmentMetricsDataHandlerTest extends TestCase
{
    #[Test]
    public function returnsTimeSeriesJson(): void
    {
        $now     = TimestampImmutable::now();
        $teamId  = TeamIdentifier::create();
        $userId  = UserIdentifier::create();
        $project = new Project(
            ProjectIdentifier::create(),
            'P',
            ServerIdentifier::create(),
            $teamId,
            $now,
            $userId,
            $now,
            $userId,
            RegistryIdentifier::create(),
        );
        $team    = new Team($teamId, 'Team', $userId, $now, $userId, $now, $userId);

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('getById')->willReturn($project);

        $metricRepository = $this->createMock(AppMetricRepository::class);
        $metricRepository->method('getAggregated')->willReturn([
            [
                't' => 1000,
                'busy' => 2.0,
                'total' => 4.0,
                'ready' => 4.0,
                'queue' => 1.0,
                'reqRate' => 0.5,
                'errPct' => 0.0,
                'latencyMs' => 120.0,
            ],
            [
                't' => 1060,
                'busy' => 3.0,
                'total' => 4.0,
                'ready' => 4.0,
                'queue' => 0.0,
                'reqRate' => 1.5,
                'errPct' => 10.0,
                'latencyMs' => 90.0,
            ],
        ]);

        $handler = new EnvironmentMetricsDataHandler($projectRepository, $metricRepository);

        $request = new Psr17Factory()->createServerRequest('GET', '/projects/x/environments/metrics-data')
            ->withAttribute('id', $project->id->toString())
            ->withAttribute('active_team', $team)
            ->withQueryParams(['branch' => 'main', 'range' => '24h']);

        $response = $handler->handle($request);

        self::assertSame(200, $response->getStatusCode());

        /** @var array<string, list<float|int>> $body */
        $body = json_decode((string) $response->getBody(), true);
        assert(is_array($body));

        self::assertSame([1000, 1060], $body['t']);
        self::assertEqualsWithDelta([2.0, 3.0], $body['busy'], 0.01);
        self::assertEqualsWithDelta([0.5, 1.5], $body['reqRate'], 0.01);
        self::assertEqualsWithDelta([0.0, 10.0], $body['errPct'], 0.01);
        self::assertEqualsWithDelta([120.0, 90.0], $body['latencyMs'], 0.01);
    }

    #[Test]
    public function returns400WhenBranchMissing(): void
    {
        $now     = TimestampImmutable::now();
        $teamId  = TeamIdentifier::create();
        $userId  = UserIdentifier::create();
        $project = new Project(
            ProjectIdentifier::create(),
            'P',
            ServerIdentifier::create(),
            $teamId,
            $now,
            $userId,
            $now,
            $userId,
            RegistryIdentifier::create(),
        );
        $team    = new Team($teamId, 'Team', $userId, $now, $userId, $now, $userId);

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('getById')->willReturn($project);
        $metricRepository = $this->createMock(AppMetricRepository::class);

        $handler = new EnvironmentMetricsDataHandler($projectRepository, $metricRepository);

        $request = new Psr17Factory()->createServerRequest('GET', '/projects/x/environments/metrics-data')
            ->withAttribute('id', $project->id->toString())
            ->withAttribute('active_team', $team);

        self::assertSame(400, $handler->handle($request)->getStatusCode());
    }
}
