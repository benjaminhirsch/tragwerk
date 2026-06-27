<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Domain\Repository;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Model\CronRun;
use Tragwerk\Domain\Repository\CronRunRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\IntegrationTestCase;

use function assert;

final class CronRunRepositoryTest extends IntegrationTestCase
{
    private CronRunRepository $repository;
    private ProjectIdentifier $projectId;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->container->get(CronRunRepository::class);
        assert($repository instanceof CronRunRepository);
        $this->repository = $repository;
        $this->projectId  = $this->seedProject();
    }

    #[Test]
    public function storesAndReturnsRunsNewestFirst(): void
    {
        $base = new DateTimeImmutable('2026-06-27 02:00:00+00');
        $this->repository->store($this->makeRun('bin/cli a', $base, succeeded: true));
        $this->repository->store($this->makeRun('bin/cli b', $base->modify('+5 minutes'), succeeded: false));

        $runs = $this->repository->recent($this->projectId, 'main');

        self::assertCount(2, $runs);
        self::assertSame('bin/cli b', $runs[0]->command);
        self::assertSame('bin/cli a', $runs[1]->command);
    }

    #[Test]
    public function upsertFillsFinishStateForSameRun(): void
    {
        $base = new DateTimeImmutable('2026-06-27 02:00:00+00');

        // First ingest: still running (no finish). Second ingest of the same run: finished.
        $this->repository->store($this->makeRun('bin/cli a', $base, succeeded: null, finishedAt: null));
        $this->repository->store(
            $this->makeRun('bin/cli a', $base, succeeded: true, finishedAt: $base->modify('+2 seconds')),
        );

        $runs = $this->repository->recent($this->projectId, 'main');

        self::assertCount(1, $runs);
        self::assertTrue($runs[0]->succeeded);
        self::assertNotNull($runs[0]->finishedAt);
    }

    #[Test]
    public function latestPerJobReturnsNewestRunPerCommand(): void
    {
        $base = new DateTimeImmutable('2026-06-27 02:00:00+00');
        $this->repository->store($this->makeRun('bin/cli a', $base, succeeded: false));
        $this->repository->store($this->makeRun('bin/cli a', $base->modify('+1 hour'), succeeded: true));

        $latest = $this->repository->latestPerJob($this->projectId, 'main');

        self::assertArrayHasKey('bin/cli a', $latest);
        self::assertTrue($latest['bin/cli a']->succeeded);
    }

    #[Test]
    public function pruneOlderThanDeletesOnlyOldRuns(): void
    {
        $now = new DateTimeImmutable();
        $this->repository->store($this->makeRun('bin/cli a', $now->modify('-40 days')));
        $this->repository->store($this->makeRun('bin/cli b', $now));

        $deleted = $this->repository->pruneOlderThan($now->modify('-30 days'));

        self::assertSame(1, $deleted);
    }

    private function makeRun(
        string $command,
        DateTimeImmutable $startedAt,
        bool|null $succeeded = true,
        DateTimeImmutable|null $finishedAt = null,
    ): CronRun {
        return new CronRun(
            projectId:  $this->projectId->toString(),
            branch:     'main',
            appSlug:    'app',
            jobName:    'job',
            command:    $command,
            schedule:   '0 2 * * *',
            startedAt:  TimestampImmutable::fromDateTime($startedAt),
            finishedAt: $finishedAt !== null ? TimestampImmutable::fromDateTime($finishedAt) : null,
            succeeded:  $succeeded,
            output:     'out',
        );
    }

    private function seedProject(): ProjectIdentifier
    {
        $now    = TimestampImmutable::now();
        $userId = UserIdentifier::create();
        $teamId = TeamIdentifier::create();

        $user     = new User($userId, 'cron@example.com', 'C', 'T', PasswordHash::create('pw'), $now, $now);
        $userRepo = $this->container->get(UserRepository::class);
        assert($userRepo instanceof UserRepository);
        $userRepo->create($user);

        $team     = new Team($teamId, 'Cron Team', $userId, $now, $userId, $now, $userId);
        $teamRepo = $this->container->get(TeamRepository::class);
        assert($teamRepo instanceof TeamRepository);
        $teamRepo->create($team);

        $project = new Project(
            ProjectIdentifier::create(),
            'Cron App',
            ServerIdentifier::create(),
            $teamId,
            $now,
            $userId,
            $now,
            $userId,
            $this->seedRegistry($teamId, $userId),
        );

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);
        $repository->create($project);

        return $project->id;
    }

    private function seedRegistry(TeamIdentifier $teamId, UserIdentifier $userId): RegistryIdentifier
    {
        $now      = TimestampImmutable::now();
        $registry = new Registry(
            RegistryIdentifier::create(),
            'Test Registry',
            'registry.example.com',
            'test-repo',
            'user',
            'pass',
            false,
            10,
            $teamId,
            $now,
            $userId,
            $now,
            $userId,
        );

        $repository = $this->container->get(RegistryRepository::class);
        assert($repository instanceof RegistryRepository);
        $repository->create($registry);

        return $registry->id;
    }
}
