<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Handler\Server;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Handler\Server\MetricsDataHandler;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Model\ServerMetricSample;
use Tragwerk\Domain\Repository\ServerMetricRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;
use function is_array;
use function json_decode;

#[AllowMockObjectsWithoutExpectations]
final class MetricsDataHandlerTest extends TestCase
{
    #[Test]
    public function returnsTimeSeriesJsonWithPercentages(): void
    {
        $now      = TimestampImmutable::now();
        $teamId   = TeamIdentifier::create();
        $serverId = ServerIdentifier::create();
        $userId   = UserIdentifier::create();

        $team   = new Team($teamId, 'Team', $userId, $now, $userId, $now, $userId);
        $server = new Server($serverId, 'Srv', '203.0.113.1', null, $teamId, $now, $userId, $now, $userId, 22);

        $serverRepository = $this->createMock(ServerRepository::class);
        $serverRepository->method('getById')->willReturn($server);

        $metricRepository = $this->createMock(ServerMetricRepository::class);
        $metricRepository->method('getRange')->willReturn([
            new ServerMetricSample($serverId, $now, 30.0, 500, 1_000, 100, 400, 0.5),
            new ServerMetricSample($serverId, $now, 60.0, 750, 1_000, 200, 400, 1.5),
        ]);

        $handler = new MetricsDataHandler($serverRepository, $metricRepository);

        $request = new Psr17Factory()->createServerRequest('GET', '/servers/x/metrics/data')
            ->withAttribute('id', $serverId->toString())
            ->withAttribute('active_team', $team)
            ->withQueryParams(['range' => '1h']);

        $response = $handler->handle($request);

        self::assertSame(200, $response->getStatusCode());

        /** @var array<string, list<float|int>> $body */
        $body = json_decode((string) $response->getBody(), true);
        assert(is_array($body));

        self::assertCount(2, $body['t']);
        // Loose equality: JSON drops the zero fraction (30.0 → 30) without JSON_PRESERVE_ZERO_FRACTION.
        self::assertEqualsWithDelta([30.0, 60.0], $body['cpu'], 0.01);
        self::assertEqualsWithDelta([50.0, 75.0], $body['mem'], 0.01);   // 500/1000, 750/1000
        self::assertEqualsWithDelta([25.0, 50.0], $body['disk'], 0.01);  // 100/400, 200/400
        self::assertEqualsWithDelta([0.5, 1.5], $body['load'], 0.01);
    }
}
