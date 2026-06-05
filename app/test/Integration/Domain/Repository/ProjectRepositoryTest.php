<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Domain\Repository;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
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
use function iterator_to_array;

final class ProjectRepositoryTest extends IntegrationTestCase
{
    private ProjectRepository $repository;
    private TeamIdentifier $teamId;
    private UserIdentifier $userId;
    private RegistryIdentifier $registryId;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);
        $this->repository = $repository;

        $this->userId = UserIdentifier::create();
        $this->teamId = TeamIdentifier::create();

        $now  = TimestampImmutable::now();
        $user = new User(
            $this->userId,
            'repo-test@example.com',
            'Repo',
            'Tester',
            PasswordHash::create('password'),
            $now,
            $now,
        );

        $userRepository = $this->container->get(UserRepository::class);
        assert($userRepository instanceof UserRepository);
        $userRepository->create($user);

        $team = new Team(
            $this->teamId,
            'Test Team',
            $this->userId,
            $now,
            $this->userId,
            $now,
            $this->userId,
        );

        $teamRepository = $this->container->get(TeamRepository::class);
        assert($teamRepository instanceof TeamRepository);
        $teamRepository->create($team);

        $this->registryId = $this->seedRegistry();
    }

    private function seedRegistry(): RegistryIdentifier
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
            $this->teamId,
            $now,
            $this->userId,
            $now,
            $this->userId,
        );

        $repo = $this->container->get(RegistryRepository::class);
        assert($repo instanceof RegistryRepository);
        $repo->create($registry);

        return $registry->id;
    }

    #[Test]
    public function createPersistsProjectToDatabase(): void
    {
        $project = $this->makeProject();

        $this->repository->create($project);

        $found = $this->repository->getById($project->id);
        self::assertInstanceOf(Project::class, $found);
        self::assertTrue($project->id->isEqualTo($found->id));
        self::assertSame($project->name, $found->name);
        self::assertSame($project->serverId->toString(), $found->serverId->toString());
        self::assertSame($project->teamId->toString(), $found->teamId->toString());
    }

    #[Test]
    public function getByIdThrowsForUnknownId(): void
    {
        $this->expectException(EntityNotFound::class);

        $this->repository->getById(ProjectIdentifier::create());
    }

    #[Test]
    public function getAllWithoutFilterReturnsAllProjects(): void
    {
        $this->repository->create($this->makeProject(name: 'Project A'));
        $this->repository->create($this->makeProject(name: 'Project B'));

        $all = iterator_to_array($this->repository->getAll());

        self::assertCount(2, $all);
    }

    #[Test]
    public function getAllFiltersByTeamId(): void
    {
        $targetTeamId = TeamIdentifier::create();
        $otherTeamId  = TeamIdentifier::create();

        $this->repository->create($this->makeProject(name: 'Target', teamId: $targetTeamId));
        $this->repository->create($this->makeProject(name: 'Other', teamId: $otherTeamId));

        $results = iterator_to_array($this->repository->getAll(teamId: $targetTeamId));

        self::assertCount(1, $results);
        self::assertInstanceOf(Project::class, $results[0]);
        self::assertSame('Target', $results[0]->name);
    }

    #[Test]
    public function updatePersistsChangesToDatabase(): void
    {
        $project = $this->makeProject(name: 'Original');
        $this->repository->create($project);

        $project->name = 'Updated';
        $this->repository->update($project);

        $found = $this->repository->getById($project->id);
        assert($found instanceof Project);
        self::assertSame('Updated', $found->name);
    }

    #[Test]
    public function deleteRemovesProjectFromDatabase(): void
    {
        $project = $this->makeProject();
        $this->repository->create($project);

        $this->repository->delete($project->id);

        $this->expectException(EntityNotFound::class);
        $this->repository->getById($project->id);
    }

    #[Test]
    public function countProjectsByServerReturnsEmptyWhenNoProjects(): void
    {
        $teamId = TeamIdentifier::create();

        self::assertSame([], $this->repository->countProjectsByServer($teamId));
    }

    #[Test]
    public function countProjectsByServerCountsCorrectly(): void
    {
        $serverId = ServerIdentifier::create();
        $teamId   = TeamIdentifier::create();

        $this->repository->create($this->makeProject(serverId: $serverId, teamId: $teamId));
        $this->repository->create($this->makeProject(serverId: $serverId, teamId: $teamId));
        $this->repository->create($this->makeProject(serverId: ServerIdentifier::create(), teamId: $teamId));

        $counts = $this->repository->countProjectsByServer($teamId);

        self::assertSame(2, $counts[$serverId->toString()]);
    }

    #[Test]
    public function countProjectsByServerIgnoresOtherTeams(): void
    {
        $serverId    = ServerIdentifier::create();
        $teamId      = TeamIdentifier::create();
        $otherTeamId = TeamIdentifier::create();

        $this->repository->create($this->makeProject(serverId: $serverId, teamId: $teamId));
        $this->repository->create($this->makeProject(serverId: $serverId, teamId: $otherTeamId));

        $counts = $this->repository->countProjectsByServer($teamId);

        self::assertSame(1, $counts[$serverId->toString()]);
    }

    private function makeProject(
        string $name = 'Test Project',
        TeamIdentifier|null $teamId = null,
        ServerIdentifier|null $serverId = null,
    ): Project {
        $now = TimestampImmutable::now();

        return new Project(
            ProjectIdentifier::create(),
            $name,
            $serverId ?? ServerIdentifier::create(),
            $teamId ?? TeamIdentifier::create(),
            $now,
            UserIdentifier::create(),
            $now,
            UserIdentifier::create(),
            $this->registryId,
        );
    }
}
