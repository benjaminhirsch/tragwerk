<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Project;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\DeployJob;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\SwarmNode;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Enum\DeployJobStatus;
use Tragwerk\Domain\Enum\SwarmNodeRole;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\DeployJobIdentifier;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;

final class SwarmNodeHandlerTest extends AppIntegrationTestCase
{
    private const string EMAIL    = 'swarm-handler-test@example.com';
    private const string PASSWORD = 'secure-password-123';

    private User $user;
    private Team $team;
    private Server $server;
    private string $sessionCookie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user          = $this->seedUser();
        $this->team          = $this->seedTeam();
        $this->server        = $this->seedServer('Primary', '10.0.0.1');
        $this->sessionCookie = $this->loginAndGetCookie();
    }

    #[Test]
    public function addNodeToSwarmProjectReturns200(): void
    {
        $project = $this->seedSwarmProject();
        $worker  = $this->seedServer('Worker', '10.0.0.2');

        $response = $this->dispatch(
            'POST',
            $this->url('project.swarm.node.add', ['id' => $project->id->toString()]),
            ['serverId' => $worker->id->toString()],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function addNodePersistsSwarmNode(): void
    {
        $project = $this->seedSwarmProject();
        $worker  = $this->seedServer('Worker', '10.0.0.2');

        $this->dispatch(
            'POST',
            $this->url('project.swarm.node.add', ['id' => $project->id->toString()]),
            ['serverId' => $worker->id->toString()],
            $this->sessionCookie,
        );

        $repo = $this->container->get(ProjectRepository::class);
        assert($repo instanceof ProjectRepository);

        $nodes = $repo->getSwarmNodes($project->id);
        self::assertCount(1, $nodes);
        self::assertTrue($worker->id->isEqualTo($nodes[0]->serverId));
        self::assertSame(SwarmNodeRole::Worker, $nodes[0]->role);
    }

    #[Test]
    public function addNodeWithInvalidServerIdReturns200WithError(): void
    {
        $project  = $this->seedSwarmProject();
        $response = $this->dispatch(
            'POST',
            $this->url('project.swarm.node.add', ['id' => $project->id->toString()]),
            ['serverId' => 'invalid-uuid'],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());

        $repo = $this->container->get(ProjectRepository::class);
        assert($repo instanceof ProjectRepository);
        self::assertSame([], $repo->getSwarmNodes($project->id));
    }

    #[Test]
    public function addNodeToNonSwarmProjectReturns400(): void
    {
        $project  = $this->seedProject();
        $worker   = $this->seedServer('Worker', '10.0.0.2');
        $response = $this->dispatch(
            'POST',
            $this->url('project.swarm.node.add', ['id' => $project->id->toString()]),
            ['serverId' => $worker->id->toString()],
            $this->sessionCookie,
        );

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function addNodeWithUnknownProjectReturns404(): void
    {
        $worker   = $this->seedServer('Worker', '10.0.0.2');
        $response = $this->dispatch(
            'POST',
            $this->url('project.swarm.node.add', ['id' => ProjectIdentifier::create()->toString()]),
            ['serverId' => $worker->id->toString()],
            $this->sessionCookie,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function removeWorkerNodeReturns200(): void
    {
        $project = $this->seedSwarmProject();
        $worker  = $this->seedServer('Worker', '10.0.0.2');

        $repo = $this->container->get(ProjectRepository::class);
        assert($repo instanceof ProjectRepository);
        $repo->addSwarmNode(new SwarmNode($project->id, $worker->id, SwarmNodeRole::Worker, false));

        $response = $this->dispatch(
            'POST',
            $this->url('project.swarm.node.remove', [
                'id'       => $project->id->toString(),
                'serverId' => $worker->id->toString(),
            ]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function removeWorkerNodeDeletesFromDatabase(): void
    {
        $project = $this->seedSwarmProject();
        $worker  = $this->seedServer('Worker', '10.0.0.2');

        $repo = $this->container->get(ProjectRepository::class);
        assert($repo instanceof ProjectRepository);
        $repo->addSwarmNode(new SwarmNode($project->id, $worker->id, SwarmNodeRole::Worker, false));

        $this->dispatch(
            'POST',
            $this->url('project.swarm.node.remove', [
                'id'       => $project->id->toString(),
                'serverId' => $worker->id->toString(),
            ]),
            cookie: $this->sessionCookie,
        );

        self::assertSame([], $repo->getSwarmNodes($project->id));
    }

    #[Test]
    public function removeStorageNodeReturns200WithError(): void
    {
        $project = $this->seedSwarmProject();
        $storage = $this->seedServer('Storage', '10.0.0.2');

        $repo = $this->container->get(ProjectRepository::class);
        assert($repo instanceof ProjectRepository);
        $repo->addSwarmNode(new SwarmNode($project->id, $storage->id, SwarmNodeRole::Worker, true));

        $response = $this->dispatch(
            'POST',
            $this->url('project.swarm.node.remove', [
                'id'       => $project->id->toString(),
                'serverId' => $storage->id->toString(),
            ]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $repo->getSwarmNodes($project->id));
    }

    #[Test]
    public function removeManagerNodeReturns200WithError(): void
    {
        $project = $this->seedSwarmProject();
        $manager = $this->seedServer('Manager2', '10.0.0.2');

        $repo = $this->container->get(ProjectRepository::class);
        assert($repo instanceof ProjectRepository);
        $repo->addSwarmNode(new SwarmNode($project->id, $manager->id, SwarmNodeRole::Manager, false));

        $response = $this->dispatch(
            'POST',
            $this->url('project.swarm.node.remove', [
                'id'       => $project->id->toString(),
                'serverId' => $manager->id->toString(),
            ]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $repo->getSwarmNodes($project->id));
    }

    #[Test]
    public function overviewTabShowsSwarmSectionForSwarmProject(): void
    {
        $project  = $this->seedSwarmProject();
        $response = $this->dispatch(
            'GET',
            $this->url('project.show.tab', ['id' => $project->id->toString(), 'tab' => 'overview']),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Swarm', (string) $response->getBody());
    }

    #[Test]
    public function overviewTabShowsSwarmTableAfterDeploy(): void
    {
        $project = $this->seedSwarmProject();
        $this->seedCompletedDeploy($project);
        $this->seedServer('Worker', '10.0.0.2');

        $response = $this->dispatch(
            'GET',
            $this->url('project.show.tab', ['id' => $project->id->toString(), 'tab' => 'overview']),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Docker Swarm Cluster', (string) $response->getBody());
        self::assertStringNotContainsString('Add worker', (string) $response->getBody());
    }

    private function seedUser(): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            self::EMAIL,
            'Swarm',
            'Tester',
            PasswordHash::create(self::PASSWORD),
            $now,
            $now,
        );

        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);
        $repository->create($user);

        return $user;
    }

    private function seedTeam(): Team
    {
        $now  = TimestampImmutable::now();
        $team = new Team(
            TeamIdentifier::create(),
            'Test Team',
            $this->user->id,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
        );

        $repository = $this->container->get(TeamRepository::class);
        assert($repository instanceof TeamRepository);
        $repository->create($team);
        $repository->assignUsers($team->id, [$this->user->id]);

        return $team;
    }

    private function seedServer(string $name, string $host): Server
    {
        $now    = TimestampImmutable::now();
        $server = new Server(
            ServerIdentifier::create(),
            $name,
            $host,
            null,
            $this->team->id,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
        );

        $repository = $this->container->get(ServerRepository::class);
        assert($repository instanceof ServerRepository);
        $repository->create($server);

        return $server;
    }

    private function seedProject(): Project
    {
        $now     = TimestampImmutable::now();
        $project = new Project(
            ProjectIdentifier::create(),
            'Test Project',
            $this->server->id,
            $this->team->id,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
        );

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);
        $repository->create($project);

        return $project;
    }

    private function seedSwarmProject(): Project
    {
        $now     = TimestampImmutable::now();
        $project = new Project(
            ProjectIdentifier::create(),
            'Swarm Project',
            $this->server->id,
            $this->team->id,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
            swarmEnabled: true,
        );

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);
        $repository->create($project);

        return $project;
    }

    private function seedCompletedDeploy(Project $project): void
    {
        $now = TimestampImmutable::now();
        $job = new DeployJob(
            id:        DeployJobIdentifier::create(),
            projectId: $project->id,
            branch:    'main',
            commitSha: 'abc1234',
            status:    DeployJobStatus::Completed,
            output:    '',
            createdAt: $now,
            updatedAt: $now,
        );

        $repository = $this->container->get(DeployJobRepository::class);
        assert($repository instanceof DeployJobRepository);
        $repository->create($job);
    }

    private function loginAndGetCookie(): string
    {
        $response = $this->dispatch('POST', $this->url('login'), [
            'email'    => self::EMAIL,
            'password' => self::PASSWORD,
        ]);

        return $this->getSessionCookie($response);
    }
}
