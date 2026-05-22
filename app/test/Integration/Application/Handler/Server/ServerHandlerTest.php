<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Server;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\CredentialIdentifier;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;

final class ServerHandlerTest extends AppIntegrationTestCase
{
    private const string EMAIL    = 'server-test@example.com';
    private const string PASSWORD = 'secure-password-123';

    private User $user;
    private Project $project;
    private string $sessionCookie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user          = $this->seedUser();
        $this->project       = $this->seedProject();
        $this->sessionCookie = $this->loginAndGetCookie();
    }

    #[Test]
    public function listRendersServersPage(): void
    {
        $response = $this->dispatch('GET', $this->url('server'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function listRedirectsUnauthenticatedUserToLogin(): void
    {
        $response = $this->dispatch('GET', $this->url('server'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('login'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function createGetRendersForm(): void
    {
        $response = $this->dispatch('GET', $this->url('server.create'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithValidDataRedirectsToServerList(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('server.create'),
            ['name' => 'My Server', 'host' => '192.168.1.1', 'port' => '22'],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('server'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function createPostPersistsServerInDatabase(): void
    {
        $this->dispatch(
            'POST',
            $this->url('server.create'),
            ['name' => 'My Server', 'host' => '192.168.1.1', 'port' => '22'],
            $this->sessionCookie,
        );

        $repository = $this->container->get(ServerRepository::class);
        assert($repository instanceof ServerRepository);

        $servers = [...$repository->getAll(projectId: $this->project->id)];
        self::assertCount(1, $servers);
        self::assertInstanceOf(Server::class, $servers[0]);
        self::assertSame('My Server', $servers[0]->name);
        self::assertSame('192.168.1.1', $servers[0]->host);
    }

    #[Test]
    public function createPostWithEmptyNameReRendersForm(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('server.create'),
            ['name' => '', 'host' => '192.168.1.1'],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithInvalidIpReRendersForm(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('server.create'),
            ['name' => 'My Server', 'host' => 'not-an-ip'],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithDuplicateHostReRendersForm(): void
    {
        $this->seedServer('Existing Server', '10.0.0.1');

        $response = $this->dispatch(
            'POST',
            $this->url('server.create'),
            ['name' => 'New Server', 'host' => '10.0.0.1'],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function editGetRendersFormWithServerData(): void
    {
        $server   = $this->seedServer('My Server', '192.168.1.1');
        $response = $this->dispatch(
            'GET',
            $this->url('server.edit', ['id' => $server->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function editPostWithValidDataRedirectsToServerList(): void
    {
        $server   = $this->seedServer('Old Name', '192.168.1.1');
        $response = $this->dispatch(
            'POST',
            $this->url('server.edit', ['id' => $server->id->toString()]),
            ['name' => 'New Name', 'host' => '192.168.1.1', 'port' => '22'],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('server'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function editPostUpdatesServerInDatabase(): void
    {
        $server = $this->seedServer('Old Name', '192.168.1.1');
        $this->dispatch(
            'POST',
            $this->url('server.edit', ['id' => $server->id->toString()]),
            ['name' => 'Updated Name', 'host' => '10.0.0.50', 'port' => '22'],
            $this->sessionCookie,
        );

        $repository = $this->container->get(ServerRepository::class);
        assert($repository instanceof ServerRepository);

        $updated = $repository->getById($server->id);
        assert($updated instanceof Server);
        self::assertSame('Updated Name', $updated->name);
        self::assertSame('10.0.0.50', $updated->host);
    }

    #[Test]
    public function editPostWithDuplicateHostReRendersForm(): void
    {
        $this->seedServer('Server A', '192.168.1.1');
        $serverB  = $this->seedServer('Server B', '192.168.1.2');
        $response = $this->dispatch(
            'POST',
            $this->url('server.edit', ['id' => $serverB->id->toString()]),
            ['name' => 'Server B', 'host' => '192.168.1.1', 'port' => '22'],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function editPostWithSameHostSucceeds(): void
    {
        $server   = $this->seedServer('My Server', '192.168.1.1');
        $response = $this->dispatch(
            'POST',
            $this->url('server.edit', ['id' => $server->id->toString()]),
            ['name' => 'Renamed', 'host' => '192.168.1.1', 'port' => '22'],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
    }

    #[Test]
    public function editGetWithUnknownServerIdRedirectsToServerList(): void
    {
        $response = $this->dispatch(
            'GET',
            $this->url('server.edit', ['id' => ServerIdentifier::create()->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('server'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function editGetWithServerFromOtherProjectRedirectsToServerList(): void
    {
        $otherServer = $this->seedServerForProject('Foreign Server', '10.10.10.10', $this->seedOtherProject()->id);
        $response    = $this->dispatch(
            'GET',
            $this->url('server.edit', ['id' => $otherServer->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('server'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function deletePostRedirectsToServerList(): void
    {
        $server   = $this->seedServer('My Server', '192.168.1.1');
        $response = $this->dispatch(
            'POST',
            $this->url('server.delete', ['id' => $server->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('server'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function deletePostRemovesServerFromDatabase(): void
    {
        $server = $this->seedServer('My Server', '192.168.1.1');
        $this->dispatch(
            'POST',
            $this->url('server.delete', ['id' => $server->id->toString()]),
            cookie: $this->sessionCookie,
        );

        $repository = $this->container->get(ServerRepository::class);
        assert($repository instanceof ServerRepository);

        $remaining = [...$repository->getAll(projectId: $this->project->id)];
        self::assertCount(0, $remaining);
    }

    #[Test]
    public function deletePostWithUnknownServerIdRedirectsToServerList(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('server.delete', ['id' => ServerIdentifier::create()->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('server'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function deletePostWithServerFromOtherProjectDoesNotDelete(): void
    {
        $otherServer = $this->seedServerForProject('Foreign Server', '10.10.10.10', $this->seedOtherProject()->id);

        $this->dispatch(
            'POST',
            $this->url('server.delete', ['id' => $otherServer->id->toString()]),
            cookie: $this->sessionCookie,
        );

        $repository = $this->container->get(ServerRepository::class);
        assert($repository instanceof ServerRepository);

        $server = $repository->getById($otherServer->id);
        self::assertInstanceOf(Server::class, $server);
    }

    #[Test]
    public function editPostWithValidCredentialAssignsCredentialToServer(): void
    {
        $server     = $this->seedServer('My Server', '192.168.1.1');
        $credential = $this->seedCredential('Deploy Key', 'deploy');

        $this->dispatch(
            'POST',
            $this->url('server.edit', ['id' => $server->id->toString()]),
            ['name' => 'My Server', 'host' => '192.168.1.1', 'port' => '22', 'credentialId' => $credential->id->toString()], // phpcs:ignore
            $this->sessionCookie,
        );

        $repository = $this->container->get(ServerRepository::class);
        assert($repository instanceof ServerRepository);

        $updated = $repository->getById($server->id);
        assert($updated instanceof Server);
        self::assertNotNull($updated->credentialId);
        self::assertSame($credential->id->toString(), $updated->credentialId->toString());
    }

    #[Test]
    public function editPostWithCredentialFromOtherProjectReRendersForm(): void
    {
        $server            = $this->seedServer('My Server', '192.168.1.1');
        $otherProject      = $this->seedOtherProject();
        $foreignCredential = $this->seedCredentialForProject('Foreign Key', 'root', $otherProject->id);

        $response = $this->dispatch(
            'POST',
            $this->url('server.edit', ['id' => $server->id->toString()]),
            ['name' => 'My Server', 'host' => '192.168.1.1', 'port' => '22', 'credentialId' => $foreignCredential->id->toString()], // phpcs:ignore
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function editPostWithEmptyCredentialIdRemovesAssignment(): void
    {
        $credential = $this->seedCredential('Deploy Key', 'deploy');
        $server     = $this->seedServerWithCredential('My Server', '192.168.1.1', $credential->id);

        $this->dispatch(
            'POST',
            $this->url('server.edit', ['id' => $server->id->toString()]),
            ['name' => 'My Server', 'host' => '192.168.1.1', 'port' => '22', 'credentialId' => ''],
            $this->sessionCookie,
        );

        $repository = $this->container->get(ServerRepository::class);
        assert($repository instanceof ServerRepository);

        $updated = $repository->getById($server->id);
        assert($updated instanceof Server);
        self::assertNull($updated->credentialId);
    }

    #[Test]
    public function createPostWithInvalidPortReRendersForm(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('server.create'),
            ['name' => 'My Server', 'host' => '192.168.1.1', 'port' => '99999'],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostPersistsPort(): void
    {
        $this->dispatch(
            'POST',
            $this->url('server.create'),
            ['name' => 'My Server', 'host' => '192.168.1.1', 'port' => '2222'],
            $this->sessionCookie,
        );

        $repository = $this->container->get(ServerRepository::class);
        assert($repository instanceof ServerRepository);

        $servers = [...$repository->getAll(projectId: $this->project->id)];
        self::assertCount(1, $servers);
        self::assertInstanceOf(Server::class, $servers[0]);
        self::assertSame(2222, $servers[0]->port);
    }

    private function seedUser(): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            self::EMAIL,
            'Server',
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

    private function seedProject(): Project
    {
        $now     = TimestampImmutable::now();
        $project = new Project(
            ProjectIdentifier::create(),
            'Test Project',
            $this->user->id,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
        );

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);
        $repository->create($project);
        $repository->assignUsers($project->id, [$this->user->id]);

        return $project;
    }

    private function seedOtherProject(): Project
    {
        $now     = TimestampImmutable::now();
        $project = new Project(
            ProjectIdentifier::create(),
            'Other Project',
            $this->user->id,
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

    private function seedServer(string $name, string $host): Server
    {
        return $this->seedServerForProject($name, $host, $this->project->id);
    }

    private function seedServerForProject(string $name, string $host, ProjectIdentifier $projectId): Server
    {
        $now    = TimestampImmutable::now();
        $server = new Server(
            ServerIdentifier::create(),
            $name,
            $host,
            null,
            $projectId,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
            22,
        );

        $repository = $this->container->get(ServerRepository::class);
        assert($repository instanceof ServerRepository);
        $repository->create($server);

        return $server;
    }

    private function seedServerWithCredential(string $name, string $host, CredentialIdentifier $credentialId): Server
    {
        $now    = TimestampImmutable::now();
        $server = new Server(
            ServerIdentifier::create(),
            $name,
            $host,
            $credentialId,
            $this->project->id,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
            22,
        );

        $repository = $this->container->get(ServerRepository::class);
        assert($repository instanceof ServerRepository);
        $repository->create($server);

        return $server;
    }

    private function seedCredential(string $name, string $username): Credential
    {
        return $this->seedCredentialForProject($name, $username, $this->project->id);
    }

    private function seedCredentialForProject(
        string $name,
        string $username,
        ProjectIdentifier $projectId,
    ): Credential {
        $now        = TimestampImmutable::now();
        $credential = new Credential(
            CredentialIdentifier::create(),
            $name,
            $username,
            null,
            $projectId,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
        );

        $repository = $this->container->get(CredentialRepository::class);
        assert($repository instanceof CredentialRepository);
        $repository->create($credential);

        return $credential;
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
