<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Domain\Repository;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
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

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);
        $this->repository = $repository;
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
    public function isServerInUseReturnsFalseWhenNoProjectUsesServer(): void
    {
        $serverId = ServerIdentifier::create();

        self::assertFalse($this->repository->isServerInUse($serverId));
    }

    #[Test]
    public function isServerInUseReturnsTrueWhenProjectAssignedToServer(): void
    {
        $serverId = ServerIdentifier::create();
        $this->repository->create($this->makeProject(serverId: $serverId));

        self::assertTrue($this->repository->isServerInUse($serverId));
    }

    #[Test]
    public function isServerInUseReturnsFalseWhenOnlyMatchingProjectIsExcluded(): void
    {
        $serverId = ServerIdentifier::create();
        $project  = $this->makeProject(serverId: $serverId);
        $this->repository->create($project);

        self::assertFalse($this->repository->isServerInUse($serverId, excludeProjectId: $project->id));
    }

    #[Test]
    public function isServerInUseIgnoresUnrelatedExcludeProject(): void
    {
        $serverId        = ServerIdentifier::create();
        $projectOnServer = $this->makeProject(serverId: $serverId);
        $unrelated       = $this->makeProject(serverId: ServerIdentifier::create());

        $this->repository->create($projectOnServer);
        $this->repository->create($unrelated);

        // Excluding $unrelated should not affect the result — $projectOnServer still uses the server
        self::assertTrue($this->repository->isServerInUse($serverId, excludeProjectId: $unrelated->id));
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
        );
    }
}
