<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Domain\Repository;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Model\ServerMetricSample;
use Tragwerk\Domain\Repository\ServerMetricRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\IntegrationTestCase;

use function assert;

final class ServerMetricRepositoryTest extends IntegrationTestCase
{
    private ServerMetricRepository $repository;
    private ServerIdentifier $serverId;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->container->get(ServerMetricRepository::class);
        assert($repository instanceof ServerMetricRepository);
        $this->repository = $repository;
        $this->serverId   = $this->seedServer();
    }

    #[Test]
    public function storeAndGetRangeRoundTrip(): void
    {
        $this->repository->store(new ServerMetricSample(
            serverId:       $this->serverId,
            sampledAt:      TimestampImmutable::now(),
            cpuPercent:     42.5,
            memUsedBytes:   1_000,
            memTotalBytes:  2_000,
            diskUsedBytes:  3_000,
            diskTotalBytes: 4_000,
            load1:          1.25,
        ));

        $samples = $this->repository->getRange(
            $this->serverId,
            new DateTimeImmutable('-1 hour'),
            new DateTimeImmutable('+1 hour'),
        );

        self::assertCount(1, $samples);
        self::assertEqualsWithDelta(42.5, $samples[0]->cpuPercent, 0.01);
        self::assertSame(1_000, $samples[0]->memUsedBytes);
        self::assertSame(2_000, $samples[0]->memTotalBytes);
        self::assertSame(3_000, $samples[0]->diskUsedBytes);
        self::assertSame(4_000, $samples[0]->diskTotalBytes);
        self::assertEqualsWithDelta(1.25, $samples[0]->load1, 0.01);
    }

    #[Test]
    public function getRangeExcludesSamplesOutsideWindow(): void
    {
        $this->repository->store($this->sampleAt(new DateTimeImmutable('-3 hours')));

        $samples = $this->repository->getRange(
            $this->serverId,
            new DateTimeImmutable('-1 hour'),
            new DateTimeImmutable('+1 hour'),
        );

        self::assertSame([], $samples);
    }

    #[Test]
    public function pruneOlderThanDeletesOnlyOldSamples(): void
    {
        $this->repository->store($this->sampleAt(new DateTimeImmutable('-10 days')));
        $this->repository->store($this->sampleAt(new DateTimeImmutable('now')));

        $deleted = $this->repository->pruneOlderThan(new DateTimeImmutable('-7 days'));

        self::assertSame(1, $deleted);

        $remaining = $this->repository->getRange(
            $this->serverId,
            new DateTimeImmutable('-1 year'),
            new DateTimeImmutable('+1 hour'),
        );
        self::assertCount(1, $remaining);
    }

    private function sampleAt(DateTimeImmutable $when): ServerMetricSample
    {
        return new ServerMetricSample(
            serverId:       $this->serverId,
            sampledAt:      TimestampImmutable::fromDateTime($when),
            cpuPercent:     10.0,
            memUsedBytes:   1,
            memTotalBytes:  2,
            diskUsedBytes:  1,
            diskTotalBytes: 2,
            load1:          0.5,
        );
    }

    private function seedServer(): ServerIdentifier
    {
        $now = TimestampImmutable::now();

        $userRepository = $this->container->get(UserRepository::class);
        assert($userRepository instanceof UserRepository);
        $user = new User(
            UserIdentifier::create(),
            'metrics@example.test',
            'Metrics',
            'Tester',
            PasswordHash::create('secret-password'),
            $now,
            $now,
        );
        $userRepository->create($user);

        $teamRepository = $this->container->get(TeamRepository::class);
        assert($teamRepository instanceof TeamRepository);
        $team = new Team(
            TeamIdentifier::create(),
            'Metrics Team',
            $user->id,
            $now,
            $user->id,
            $now,
            $user->id,
        );
        $teamRepository->create($team);

        $serverRepository = $this->container->get(ServerRepository::class);
        assert($serverRepository instanceof ServerRepository);
        $server = new Server(
            ServerIdentifier::create(),
            'Metrics Server',
            '203.0.113.10',
            null,
            $team->id,
            $now,
            $user->id,
            $now,
            $user->id,
            22,
        );
        $serverRepository->create($server);

        return $server->id;
    }
}
