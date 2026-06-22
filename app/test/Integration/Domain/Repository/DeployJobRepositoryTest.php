<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Domain\Repository;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\DeployJob;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Enum\DeployJobStatus;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\DeployJobIdentifier;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\IntegrationTestCase;

use function assert;

final class DeployJobRepositoryTest extends IntegrationTestCase
{
    private DeployJobRepository $repository;
    private ProjectIdentifier $projectId;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->container->get(DeployJobRepository::class);
        assert($repository instanceof DeployJobRepository);
        $this->repository = $repository;

        $this->projectId = $this->seedProject();
    }

    #[Test]
    public function getLatestByProjectReturnsNewestAcrossBranches(): void
    {
        $this->repository->create($this->job('main', DeployJobStatus::Completed, '2026-01-01T10:00:00.500000+00:00'));
        $this->repository->create($this->job('feature', DeployJobStatus::Running, '2026-01-01T12:00:00.500000+00:00'));
        $this->repository->create($this->job('main', DeployJobStatus::Failed, '2026-01-01T11:00:00.500000+00:00'));

        $latest = $this->repository->getLatestByProject($this->projectId);

        self::assertInstanceOf(DeployJob::class, $latest);
        self::assertSame('feature', $latest->branch);
    }

    #[Test]
    public function getLatestByProjectReturnsNullWithoutJobs(): void
    {
        self::assertNull($this->repository->getLatestByProject($this->projectId));
    }

    #[Test]
    public function getRecentByProjectsReturnsNewestFirstWithinLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $at = '2026-01-01T10:0' . $i . ':00.500000+00:00';
            $this->repository->create($this->job('main', DeployJobStatus::Completed, $at));
        }

        $recent = $this->repository->getRecentByProjects([$this->projectId->toString()], 3);

        self::assertCount(3, $recent);
        self::assertGreaterThan(
            $recent[1]->createdAt->format('U'),
            $recent[0]->createdAt->format('U'),
        );
        self::assertStringStartsWith('2026-01-01 10:04:00', $recent[0]->createdAt->toString());
    }

    #[Test]
    public function getRecentByProjectsReturnsEmptyForNoProjectIds(): void
    {
        self::assertSame([], $this->repository->getRecentByProjects([], 10));
    }

    #[Test]
    public function countByProjectsSinceCountsByStatusWithinWindow(): void
    {
        $this->repository->create($this->job('main', DeployJobStatus::Completed, '2026-01-10T10:00:00.500000+00:00'));
        $this->repository->create($this->job('main', DeployJobStatus::Failed, '2026-01-10T11:00:00.500000+00:00'));
        $this->repository->create($this->job('main', DeployJobStatus::Completed, '2026-01-10T12:00:00.500000+00:00'));
        // Outside the window — must be ignored.
        $this->repository->create($this->job('main', DeployJobStatus::Completed, '2026-01-01T00:00:00.500000+00:00'));

        $counts = $this->repository->countByProjectsSince(
            [$this->projectId->toString()],
            new \DateTimeImmutable('2026-01-05T00:00:00+00:00'),
        );

        self::assertSame(3, $counts['total']);
        self::assertSame(2, $counts['completed']);
        self::assertSame(1, $counts['failed']);
    }

    private function job(string $branch, DeployJobStatus $status, string $at): DeployJob
    {
        $ts = TimestampImmutable::fromString($at);

        return new DeployJob(
            DeployJobIdentifier::create(),
            $this->projectId,
            $branch,
            'abcdef1234567890abcdef1234567890abcdef12',
            $status,
            '',
            $ts,
            $ts,
        );
    }

    private function seedProject(): ProjectIdentifier
    {
        $now    = TimestampImmutable::now();
        $userId = UserIdentifier::create();
        $teamId = TeamIdentifier::create();

        $users = $this->container->get(UserRepository::class);
        assert($users instanceof UserRepository);
        $users->create(new User(
            $userId,
            'deployjob-test@example.com',
            'Deploy',
            'Tester',
            PasswordHash::create('password'),
            $now,
            $now,
        ));

        $teams = $this->container->get(TeamRepository::class);
        assert($teams instanceof TeamRepository);
        $teams->create(new Team($teamId, 'Test Team', $userId, $now, $userId, $now, $userId));

        $registryId = RegistryIdentifier::create();
        $registries = $this->container->get(RegistryRepository::class);
        assert($registries instanceof RegistryRepository);
        $registries->create(new Registry(
            $registryId,
            'Reg',
            'registry.example.com',
            'repo',
            'user',
            'pass',
            false,
            10,
            $teamId,
            $now,
            $userId,
            $now,
            $userId,
        ));

        $projectId = ProjectIdentifier::create();
        $projects  = $this->container->get(ProjectRepository::class);
        assert($projects instanceof ProjectRepository);
        $projects->create(new Project(
            $projectId,
            'Test Project',
            ServerIdentifier::create(),
            $teamId,
            $now,
            $userId,
            $now,
            $userId,
            $registryId,
        ));

        return $projectId;
    }
}
