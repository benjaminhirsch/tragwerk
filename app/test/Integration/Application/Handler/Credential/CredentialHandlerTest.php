<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Credential;

use phpseclib3\Crypt\EC;
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

final class CredentialHandlerTest extends AppIntegrationTestCase
{
    private const string EMAIL    = 'credential-test@example.com';
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
    public function listRendersCredentialsPage(): void
    {
        $response = $this->dispatch('GET', $this->url('credential'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function listRedirectsUnauthenticatedUserToLogin(): void
    {
        $response = $this->dispatch('GET', $this->url('credential'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('login'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function createGetRendersForm(): void
    {
        $response = $this->dispatch('GET', $this->url('credential.create'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithValidDataRedirectsToCredentialList(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('credential.create'),
            ['name' => 'Deploy Key', 'username' => 'deploy', 'privateKey' => self::makeSshPrivateKey()],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('credential'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function createPostPersistsCredentialInDatabase(): void
    {
        $this->dispatch(
            'POST',
            $this->url('credential.create'),
            ['name' => 'Deploy Key', 'username' => 'deploy', 'privateKey' => self::makeSshPrivateKey()],
            $this->sessionCookie,
        );

        $repository = $this->container->get(CredentialRepository::class);
        assert($repository instanceof CredentialRepository);

        $credentials = [...$repository->getAll(projectId: $this->project->id)];
        self::assertCount(1, $credentials);
        self::assertInstanceOf(Credential::class, $credentials[0]);
        self::assertSame('Deploy Key', $credentials[0]->name);
        self::assertSame('deploy', $credentials[0]->username);
    }

    #[Test]
    public function createPostWithEmptyNameReRendersForm(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('credential.create'),
            ['name' => '', 'username' => 'deploy', 'privateKey' => self::makeSshPrivateKey()],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithEmptyUsernameReRendersForm(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('credential.create'),
            ['name' => 'Deploy Key', 'username' => '', 'privateKey' => self::makeSshPrivateKey()],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithMissingKeyReRendersForm(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('credential.create'),
            ['name' => 'Deploy Key', 'username' => 'deploy'],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithOnlyKeyRedirectsToCredentialList(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('credential.create'),
            ['name' => 'Deploy Key', 'username' => 'deploy', 'privateKey' => self::makeSshPrivateKey()],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithGarbageKeyReRendersForm(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('credential.create'),
            ['name' => 'Deploy Key', 'username' => 'deploy', 'privateKey' => 'not-a-key'],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithPublicKeyFormatReRendersForm(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('credential.create'),
            ['name' => 'Deploy Key', 'username' => 'deploy', 'privateKey' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5 test@example.com'], //phpcs:ignore
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function editPostWithInvalidPrivateKeyReRendersForm(): void
    {
        $credential = $this->seedCredential('My Credential', 'admin');
        $response   = $this->dispatch(
            'POST',
            $this->url('credential.edit', ['id' => $credential->id->toString()]),
            [
                'name'       => 'My Credential',
                'username'   => 'admin',
                'privateKey' => 'not-a-valid-key',
            ],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function editPostWithMissingKeyReRendersForm(): void
    {
        $credential = $this->seedCredential('My Credential', 'admin');
        $response   = $this->dispatch(
            'POST',
            $this->url('credential.edit', ['id' => $credential->id->toString()]),
            ['name' => 'My Credential', 'username' => 'admin'],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function editGetRendersFormWithCredentialData(): void
    {
        $credential = $this->seedCredential('My Credential', 'admin');
        $response   = $this->dispatch(
            'GET',
            $this->url('credential.edit', ['id' => $credential->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function editPostWithValidDataRedirectsToCredentialList(): void
    {
        $credential = $this->seedCredential('Old Name', 'admin');
        $response   = $this->dispatch(
            'POST',
            $this->url('credential.edit', ['id' => $credential->id->toString()]),
            ['name' => 'New Name', 'username' => 'root', 'privateKey' => self::makeSshPrivateKey()],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('credential'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function editPostUpdatesCredentialInDatabase(): void
    {
        $credential = $this->seedCredential('Old Name', 'admin');
        $this->dispatch(
            'POST',
            $this->url('credential.edit', ['id' => $credential->id->toString()]),
            ['name' => 'Updated Name', 'username' => 'root', 'privateKey' => self::makeSshPrivateKey()],
            $this->sessionCookie,
        );

        $repository = $this->container->get(CredentialRepository::class);
        assert($repository instanceof CredentialRepository);

        $updated = $repository->getById($credential->id);
        assert($updated instanceof Credential);
        self::assertSame('Updated Name', $updated->name);
        self::assertSame('root', $updated->username);
    }

    #[Test]
    public function editGetWithUnknownCredentialIdRedirectsToCredentialList(): void
    {
        $response = $this->dispatch(
            'GET',
            $this->url('credential.edit', ['id' => CredentialIdentifier::create()->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('credential'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function editGetWithCredentialFromOtherProjectRedirectsToCredentialList(): void
    {
        $otherCredential = $this->seedCredentialForProject('Foreign Cred', 'user', $this->seedOtherProject()->id);
        $response        = $this->dispatch(
            'GET',
            $this->url('credential.edit', ['id' => $otherCredential->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('credential'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function deletePostRedirectsToCredentialList(): void
    {
        $credential = $this->seedCredential('My Credential', 'admin');
        $response   = $this->dispatch(
            'POST',
            $this->url('credential.delete', ['id' => $credential->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('credential'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function deletePostRemovesCredentialFromDatabase(): void
    {
        $credential = $this->seedCredential('My Credential', 'admin');
        $this->dispatch(
            'POST',
            $this->url('credential.delete', ['id' => $credential->id->toString()]),
            cookie: $this->sessionCookie,
        );

        $repository = $this->container->get(CredentialRepository::class);
        assert($repository instanceof CredentialRepository);

        $remaining = [...$repository->getAll(projectId: $this->project->id)];
        self::assertCount(0, $remaining);
    }

    #[Test]
    public function deletePostWithUnknownCredentialIdRedirectsToCredentialList(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('credential.delete', ['id' => CredentialIdentifier::create()->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('credential'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function deletePostWithCredentialFromOtherProjectDoesNotDelete(): void
    {
        $otherCredential = $this->seedCredentialForProject('Foreign Cred', 'user', $this->seedOtherProject()->id);

        $this->dispatch(
            'POST',
            $this->url('credential.delete', ['id' => $otherCredential->id->toString()]),
            cookie: $this->sessionCookie,
        );

        $repository = $this->container->get(CredentialRepository::class);
        assert($repository instanceof CredentialRepository);

        $credential = $repository->getById($otherCredential->id);
        self::assertInstanceOf(Credential::class, $credential);
    }

    #[Test]
    public function deletePostBlockedWhenCredentialAssignedToServerRedirectsToEditPage(): void
    {
        $credential = $this->seedCredential('Deploy Key', 'deploy');
        $this->seedServerWithCredential($credential->id);

        $response = $this->dispatch(
            'POST',
            $this->url('credential.delete', ['id' => $credential->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString(
            $this->url('credential.edit', ['id' => $credential->id->toString()]),
            $response->getHeaderLine('Location'),
        );

        $repository = $this->container->get(CredentialRepository::class);
        assert($repository instanceof CredentialRepository);

        $still = $repository->getById($credential->id);
        self::assertInstanceOf(Credential::class, $still);
    }

    private function seedUser(): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            self::EMAIL,
            'Credential',
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

    private function seedCredential(string $name, string $username): Credential
    {
        return $this->seedCredentialForProject($name, $username, $this->project->id);
    }

    private function seedCredentialForProject(string $name, string $username, ProjectIdentifier $projectId): Credential
    {
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

    private static function makeSshPrivateKey(): string
    {
        return EC::createKey('Ed25519')->toString('OpenSSH');
    }

    private function seedServerWithCredential(CredentialIdentifier $credentialId): Server
    {
        $now    = TimestampImmutable::now();
        $server = new Server(
            ServerIdentifier::create(),
            'Test Server',
            '10.0.0.1',
            $credentialId,
            $this->project->id,
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

    private function loginAndGetCookie(): string
    {
        $response = $this->dispatch('POST', $this->url('login'), [
            'email'    => self::EMAIL,
            'password' => self::PASSWORD,
        ]);

        return $this->getSessionCookie($response);
    }
}
