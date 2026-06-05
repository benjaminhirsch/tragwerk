<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Project;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;

final class ProjectHandlerTest extends AppIntegrationTestCase
{
    private const string EMAIL    = 'project-test@example.com';
    private const string PASSWORD = 'secure-password-123';

    private User $user;
    private Team $team;
    private Server $server;
    private Registry $registry;
    private string $sessionCookie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user          = $this->seedUser();
        $this->team          = $this->seedTeam();
        $this->server        = $this->seedServer();
        $this->registry      = $this->seedRegistry();
        $this->sessionCookie = $this->loginAndGetCookie();
    }

    #[Test]
    public function listRendersProjectsPage(): void
    {
        $response = $this->dispatch('GET', $this->url('project'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function listRedirectsUnauthenticatedUserToLogin(): void
    {
        $response = $this->dispatch('GET', $this->url('project'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('login'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function createGetRendersForm(): void
    {
        $response = $this->dispatch('GET', $this->url('project.create'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithValidDataRedirectsToProjectShow(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('project.create'),
            [
                'name'       => 'My Project',
                'serverId'   => $this->server->id->toString(),
                'registryId' => $this->registry->id->toString(),
            ],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertMatchesRegularExpression(
            '#^/projects/[0-9a-f-]{36}$#',
            $response->getHeaderLine('Location'),
        );
    }

    #[Test]
    public function createPostPersistsProjectInDatabase(): void
    {
        $this->dispatch(
            'POST',
            $this->url('project.create'),
            [
                'name'       => 'My Project',
                'serverId'   => $this->server->id->toString(),
                'registryId' => $this->registry->id->toString(),
            ],
            $this->sessionCookie,
        );

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);

        $projects = [...$repository->getAll(teamId: $this->team->id)];
        self::assertCount(1, $projects);
        self::assertInstanceOf(Project::class, $projects[0]);
        self::assertSame('My Project', $projects[0]->name);
        self::assertSame($this->server->id->toString(), $projects[0]->serverId->toString());
    }

    #[Test]
    public function createPostWithEmptyNameReRendersForm(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('project.create'),
            ['name' => '', 'serverId' => $this->server->id->toString()],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithNoServerSelectedReRendersForm(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('project.create'),
            ['name' => 'My Project', 'serverId' => ''],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithAlreadyUsedServerSucceeds(): void
    {
        $this->seedProject('Existing Project');

        $response = $this->dispatch(
            'POST',
            $this->url('project.create'),
            [
                'name'       => 'New Project',
                'serverId'   => $this->server->id->toString(),
                'registryId' => $this->registry->id->toString(),
            ],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
    }

    #[Test]
    public function editPostWithAlreadyUsedServerSucceeds(): void
    {
        $occupyingProject = $this->seedProject('Occupying Project');
        $secondServer     = $this->seedExtraServer();
        $editedProject    = $this->seedProjectForTeam($this->team->id, 'Edited Project', $secondServer->id);

        $response = $this->dispatch(
            'POST',
            $this->url('project.edit', ['id' => $editedProject->id->toString()]),
            [
                'name'       => 'Edited Project',
                'serverId'   => $occupyingProject->serverId->toString(),
                'registryId' => $this->registry->id->toString(),
            ],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
    }

    #[Test]
    public function showGetRendersDetailPage(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'GET',
            $this->url('project.show', ['id' => $project->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function showGetWithUnknownIdRedirectsToProjectList(): void
    {
        $response = $this->dispatch(
            'GET',
            $this->url('project.show', ['id' => ProjectIdentifier::create()->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('project'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function showGetWithProjectFromOtherTeamRedirectsToProjectList(): void
    {
        $foreign  = $this->seedProjectForTeam($this->seedOtherTeam()->id);
        $response = $this->dispatch(
            'GET',
            $this->url('project.show', ['id' => $foreign->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('project'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function showTabOverviewRendersContent(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'GET',
            $this->url('project.show.tab', ['id' => $project->id->toString(), 'tab' => 'overview']),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function editGetRendersForm(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'GET',
            $this->url('project.edit', ['id' => $project->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function editGetWithUnknownIdRedirectsToProjectList(): void
    {
        $response = $this->dispatch(
            'GET',
            $this->url('project.edit', ['id' => ProjectIdentifier::create()->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('project'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function editGetWithProjectFromOtherTeamRedirectsToProjectList(): void
    {
        $foreign  = $this->seedProjectForTeam($this->seedOtherTeam()->id);
        $response = $this->dispatch(
            'GET',
            $this->url('project.edit', ['id' => $foreign->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('project'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function editPostWithValidDataRedirectsToProjectShow(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'POST',
            $this->url('project.edit', ['id' => $project->id->toString()]),
            [
                'name'       => 'Updated Project',
                'serverId'   => $this->server->id->toString(),
                'registryId' => $this->registry->id->toString(),
            ],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(
            $this->url('project.show', ['id' => $project->id->toString()]),
            $response->getHeaderLine('Location'),
        );
    }

    #[Test]
    public function editPostUpdatesProjectInDatabase(): void
    {
        $project = $this->seedProject('Original Name');
        $this->dispatch(
            'POST',
            $this->url('project.edit', ['id' => $project->id->toString()]),
            [
                'name'       => 'Updated Name',
                'serverId'   => $this->server->id->toString(),
                'registryId' => $this->registry->id->toString(),
            ],
            $this->sessionCookie,
        );

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);

        $updated = $repository->getById($project->id);
        assert($updated instanceof Project);
        self::assertSame('Updated Name', $updated->name);
    }

    #[Test]
    public function editPostWithEmptyNameReRendersForm(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'POST',
            $this->url('project.edit', ['id' => $project->id->toString()]),
            ['name' => '', 'serverId' => $this->server->id->toString()],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function deletePostRedirectsToProjectList(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'POST',
            $this->url('project.delete', ['id' => $project->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('project'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function deletePostRemovesProjectFromDatabase(): void
    {
        $project = $this->seedProject();
        $this->dispatch(
            'POST',
            $this->url('project.delete', ['id' => $project->id->toString()]),
            cookie: $this->sessionCookie,
        );

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);

        $remaining = [...$repository->getAll(teamId: $this->team->id)];
        self::assertCount(0, $remaining);
    }

    #[Test]
    public function deletePostWithUnknownIdRedirectsToProjectList(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('project.delete', ['id' => ProjectIdentifier::create()->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('project'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function deletePostWithProjectFromOtherTeamDoesNotDelete(): void
    {
        $foreign = $this->seedProjectForTeam($this->seedOtherTeam()->id);

        $this->dispatch(
            'POST',
            $this->url('project.delete', ['id' => $foreign->id->toString()]),
            cookie: $this->sessionCookie,
        );

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);

        $still = $repository->getById($foreign->id);
        self::assertInstanceOf(Project::class, $still);
    }

    private function seedRegistry(): Registry
    {
        $now      = TimestampImmutable::now();
        $registry = new Registry(
            RegistryIdentifier::create(),
            'Test Registry',
            'registry.example.com',
            'my-repo',
            'user',
            'pass',
            false,
            10,
            $this->team->id,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
        );

        $repository = $this->container->get(RegistryRepository::class);
        assert($repository instanceof RegistryRepository);
        $repository->create($registry);

        return $registry;
    }

    private function seedUser(): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            self::EMAIL,
            'Project',
            'Tester',
            PasswordHash::create(self::PASSWORD),
            $now,
            $now,
        );

        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);
        $repository->create($user);
        $repository->confirm($user->id);

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

    private function seedOtherTeam(): Team
    {
        $now  = TimestampImmutable::now();
        $team = new Team(
            TeamIdentifier::create(),
            'Other Team',
            $this->user->id,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
        );

        $repository = $this->container->get(TeamRepository::class);
        assert($repository instanceof TeamRepository);
        $repository->create($team);

        return $team;
    }

    private function seedServer(): Server
    {
        $now    = TimestampImmutable::now();
        $server = new Server(
            ServerIdentifier::create(),
            'Test Server',
            '10.0.0.1',
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

    private function seedProject(string $name = 'Test Project'): Project
    {
        return $this->seedProjectForTeam($this->team->id, $name);
    }

    private function seedExtraServer(string $name = 'Extra Test Server', string $host = '10.0.0.2'): Server
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

    private function seedProjectForTeam(
        TeamIdentifier $teamId,
        string $name = 'Test Project',
        ServerIdentifier|null $serverId = null,
    ): Project {
        $now     = TimestampImmutable::now();
        $project = new Project(
            ProjectIdentifier::create(),
            $name,
            $serverId ?? $this->server->id,
            $teamId,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
            $this->registry->id,
        );

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);
        $repository->create($project);

        return $project;
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
