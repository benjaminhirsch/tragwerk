<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Domain\Repository;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\SwarmNode;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Enum\SwarmNodeRole;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
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
    private ServerRepository $serverRepository;
    private TeamIdentifier $teamId;
    private UserIdentifier $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);
        $this->repository = $repository;

        $serverRepository = $this->container->get(ServerRepository::class);
        assert($serverRepository instanceof ServerRepository);
        $this->serverRepository = $serverRepository;

        $this->userId = UserIdentifier::create();
        $this->teamId = TeamIdentifier::create();

        $now            = TimestampImmutable::now();
        $user           = new User(
            $this->userId,
            'swarm-test@example.com',
            'Swarm',
            'Tester',
            PasswordHash::create('password'),
            $now,
            $now,
        );
        $userRepository = $this->container->get(UserRepository::class);
        assert($userRepository instanceof UserRepository);
        $userRepository->create($user);

        $team           = new Team(
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

    #[Test]
    public function addSwarmNodePersistsToDatabase(): void
    {
        $project = $this->makeProject();
        $this->repository->create($project);
        $server = $this->makeServer();
        $this->serverRepository->create($server);

        $node = new SwarmNode($project->id, $server->id, SwarmNodeRole::Worker, false);
        $this->repository->addSwarmNode($node);

        $nodes = $this->repository->getSwarmNodes($project->id);
        self::assertCount(1, $nodes);
        self::assertTrue($server->id->isEqualTo($nodes[0]->serverId));
        self::assertSame(SwarmNodeRole::Worker, $nodes[0]->role);
        self::assertFalse($nodes[0]->isStorage);
    }

    #[Test]
    public function getSwarmNodesReturnsEmptyArrayWhenNoneExist(): void
    {
        $project = $this->makeProject();
        $this->repository->create($project);

        self::assertSame([], $this->repository->getSwarmNodes($project->id));
    }

    #[Test]
    public function removeSwarmNodeDeletesFromDatabase(): void
    {
        $project = $this->makeProject();
        $this->repository->create($project);
        $server = $this->makeServer();
        $this->serverRepository->create($server);

        $this->repository->addSwarmNode(new SwarmNode($project->id, $server->id, SwarmNodeRole::Worker, false));
        $this->repository->removeSwarmNode($project->id, $server->id);

        self::assertSame([], $this->repository->getSwarmNodes($project->id));
    }

    #[Test]
    public function removeSwarmNodeThrowsForUnknownNode(): void
    {
        $project = $this->makeProject();
        $this->repository->create($project);

        $this->expectException(EntityNotFound::class);
        $this->repository->removeSwarmNode($project->id, ServerIdentifier::create());
    }

    #[Test]
    public function getSwarmStorageNodeReturnsStorageNode(): void
    {
        $project = $this->makeProject();
        $this->repository->create($project);
        $server = $this->makeServer();
        $this->serverRepository->create($server);

        $this->repository->addSwarmNode(new SwarmNode($project->id, $server->id, SwarmNodeRole::Manager, true));

        $storageNode = $this->repository->getSwarmStorageNode($project->id);
        self::assertInstanceOf(SwarmNode::class, $storageNode);
        self::assertTrue($server->id->isEqualTo($storageNode->serverId));
        self::assertTrue($storageNode->isStorage);
    }

    #[Test]
    public function getSwarmStorageNodeReturnsNullWhenNoStorageNodeSet(): void
    {
        $project = $this->makeProject();
        $this->repository->create($project);

        self::assertNull($this->repository->getSwarmStorageNode($project->id));
    }

    #[Test]
    public function swarmNodeExistsReturnsTrueForExistingNode(): void
    {
        $project = $this->makeProject();
        $this->repository->create($project);
        $server = $this->makeServer();
        $this->serverRepository->create($server);

        $this->repository->addSwarmNode(new SwarmNode($project->id, $server->id, SwarmNodeRole::Worker, false));

        self::assertTrue($this->repository->swarmNodeExists($project->id, $server->id));
    }

    #[Test]
    public function swarmNodeExistsReturnsFalseForMissingNode(): void
    {
        $project = $this->makeProject();
        $this->repository->create($project);

        self::assertFalse($this->repository->swarmNodeExists($project->id, ServerIdentifier::create()));
    }

    #[Test]
    public function swarmEnabledDefaultsToFalse(): void
    {
        $project = $this->makeProject();
        $this->repository->create($project);

        $found = $this->repository->getById($project->id);
        assert($found instanceof Project);
        self::assertFalse($found->swarmEnabled);
    }

    #[Test]
    public function swarmEnabledPersistsTrueValue(): void
    {
        $project               = $this->makeProject();
        $project->swarmEnabled = true;
        $this->repository->create($project);

        $found = $this->repository->getById($project->id);
        assert($found instanceof Project);
        self::assertTrue($found->swarmEnabled);
    }

    private function makeServer(): Server
    {
        $now = TimestampImmutable::now();

        return new Server(
            ServerIdentifier::create(),
            'Test Server',
            '10.0.0.1',
            null,
            $this->teamId,
            $now,
            $this->userId,
            $now,
            $this->userId,
        );
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
