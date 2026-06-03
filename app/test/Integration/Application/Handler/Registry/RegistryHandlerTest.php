<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Registry;

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

final class RegistryHandlerTest extends AppIntegrationTestCase
{
    private const string EMAIL    = 'registry-test@example.com';
    private const string PASSWORD = 'secure-password-123';

    private User $user;
    private Team $team;
    private string $sessionCookie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user          = $this->seedUser();
        $this->team          = $this->seedTeam();
        $this->sessionCookie = $this->loginAndGetCookie();
    }

    #[Test]
    public function listRendersRegistriesPage(): void
    {
        $response = $this->dispatch('GET', $this->url('registry'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function listRedirectsUnauthenticatedUserToLogin(): void
    {
        $response = $this->dispatch('GET', $this->url('registry'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('login'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function createGetRendersForm(): void
    {
        $response = $this->dispatch('GET', $this->url('registry.create'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithValidDataRedirectsToRegistryList(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('registry.create'),
            $this->validRegistryData(),
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('registry'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function createPostPersistsRegistryInDatabase(): void
    {
        $this->dispatch(
            'POST',
            $this->url('registry.create'),
            $this->validRegistryData(),
            $this->sessionCookie,
        );

        $repository = $this->container->get(RegistryRepository::class);
        assert($repository instanceof RegistryRepository);

        $registries = [...$repository->getAll($this->team->id)];
        self::assertCount(1, $registries);
        self::assertInstanceOf(Registry::class, $registries[0]);
        self::assertSame('My Registry', $registries[0]->name);
        self::assertSame('docker.io', $registries[0]->url);
        self::assertSame('acme/myapp', $registries[0]->repository);
    }

    #[Test]
    public function createPostWithEmptyNameReRendersForm(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('registry.create'),
            [...$this->validRegistryData(), 'name' => ''],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithEmptyPasswordReRendersForm(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('registry.create'),
            [...$this->validRegistryData(), 'password' => ''],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithEmptyUrlReRendersForm(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('registry.create'),
            [...$this->validRegistryData(), 'url' => ''],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithEmptyRepositoryReRendersForm(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('registry.create'),
            [...$this->validRegistryData(), 'repository' => ''],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function editGetRendersForm(): void
    {
        $registry = $this->seedRegistry();
        $response = $this->dispatch(
            'GET',
            $this->url('registry.edit', ['id' => $registry->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function editGetPreFillsFormWithExistingData(): void
    {
        $registry = $this->seedRegistry(keepTags: 42);
        $response = $this->dispatch(
            'GET',
            $this->url('registry.edit', ['id' => $registry->id->toString()]),
            cookie: $this->sessionCookie,
        );

        $body = (string) $response->getBody();
        self::assertStringContainsString('42', $body);
    }

    #[Test]
    public function editGetWithUnknownIdRedirectsToRegistryList(): void
    {
        $response = $this->dispatch(
            'GET',
            $this->url('registry.edit', ['id' => RegistryIdentifier::create()->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('registry'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function editGetWithRegistryFromOtherTeamRedirectsToRegistryList(): void
    {
        $foreign  = $this->seedRegistryForTeam($this->seedOtherTeam()->id);
        $response = $this->dispatch(
            'GET',
            $this->url('registry.edit', ['id' => $foreign->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('registry'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function editPostWithValidDataRedirectsToRegistryList(): void
    {
        $registry = $this->seedRegistry();
        $response = $this->dispatch(
            'POST',
            $this->url('registry.edit', ['id' => $registry->id->toString()]),
            $this->validRegistryData('Updated Registry'),
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('registry'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function editPostUpdatesRegistryInDatabase(): void
    {
        $registry = $this->seedRegistry();
        $this->dispatch(
            'POST',
            $this->url('registry.edit', ['id' => $registry->id->toString()]),
            $this->validRegistryData('Updated Name'),
            $this->sessionCookie,
        );

        $repository = $this->container->get(RegistryRepository::class);
        assert($repository instanceof RegistryRepository);

        $updated = $repository->getById($registry->id);
        assert($updated instanceof Registry);
        self::assertSame('Updated Name', $updated->name);
    }

    #[Test]
    public function editPostWithEmptyPasswordKeepsExistingPassword(): void
    {
        $registry = $this->seedRegistry();
        $this->dispatch(
            'POST',
            $this->url('registry.edit', ['id' => $registry->id->toString()]),
            [...$this->validRegistryData(), 'password' => ''],
            $this->sessionCookie,
        );

        $repository = $this->container->get(RegistryRepository::class);
        assert($repository instanceof RegistryRepository);

        $updated = $repository->getById($registry->id);
        assert($updated instanceof Registry);
        self::assertSame('secret-token', $updated->password);
    }

    #[Test]
    public function editPostWithEmptyNameReRendersForm(): void
    {
        $registry = $this->seedRegistry();
        $response = $this->dispatch(
            'POST',
            $this->url('registry.edit', ['id' => $registry->id->toString()]),
            [...$this->validRegistryData(), 'name' => ''],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function deletePostRedirectsToRegistryList(): void
    {
        $registry = $this->seedRegistry();
        $response = $this->dispatch(
            'POST',
            $this->url('registry.delete', ['id' => $registry->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('registry'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function deletePostRemovesRegistryFromDatabase(): void
    {
        $registry = $this->seedRegistry();
        $this->dispatch(
            'POST',
            $this->url('registry.delete', ['id' => $registry->id->toString()]),
            cookie: $this->sessionCookie,
        );

        $repository = $this->container->get(RegistryRepository::class);
        assert($repository instanceof RegistryRepository);

        $remaining = [...$repository->getAll($this->team->id)];
        self::assertCount(0, $remaining);
    }

    #[Test]
    public function deletePostWithUnknownIdRedirectsToRegistryList(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('registry.delete', ['id' => RegistryIdentifier::create()->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('registry'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function deletePostWithRegistryFromOtherTeamDoesNotDelete(): void
    {
        $foreign = $this->seedRegistryForTeam($this->seedOtherTeam()->id);

        $this->dispatch(
            'POST',
            $this->url('registry.delete', ['id' => $foreign->id->toString()]),
            cookie: $this->sessionCookie,
        );

        $repository = $this->container->get(RegistryRepository::class);
        assert($repository instanceof RegistryRepository);

        $still = $repository->getById($foreign->id);
        self::assertInstanceOf(Registry::class, $still);
    }

    #[Test]
    public function deletePostBlockedWhenAssignedToProjectRedirectsToEditPage(): void
    {
        $registry = $this->seedRegistry();
        $this->seedProjectWithRegistry($registry->id);

        $response = $this->dispatch(
            'POST',
            $this->url('registry.delete', ['id' => $registry->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString(
            $this->url('registry.edit', ['id' => $registry->id->toString()]),
            $response->getHeaderLine('Location'),
        );

        $repository = $this->container->get(RegistryRepository::class);
        assert($repository instanceof RegistryRepository);

        $still = $repository->getById($registry->id);
        self::assertInstanceOf(Registry::class, $still);
    }

    /** @return array<string, string> */
    private function validRegistryData(string $name = 'My Registry'): array
    {
        return [
            'name'       => $name,
            'url'        => 'docker.io',
            'repository' => 'acme/myapp',
            'username'   => 'acme',
            'password'   => 'secret-token',
        ];
    }

    private function seedUser(): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            self::EMAIL,
            'Registry',
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

    private function seedRegistry(string $name = 'Test Registry', int $keepTags = 10): Registry
    {
        return $this->seedRegistryForTeam($this->team->id, $name, $keepTags);
    }

    private function seedRegistryForTeam(
        TeamIdentifier $teamId,
        string $name = 'Test Registry',
        int $keepTags = 10,
    ): Registry {
        $now      = TimestampImmutable::now();
        $registry = new Registry(
            RegistryIdentifier::create(),
            $name,
            'docker.io',
            'acme/myapp',
            'acme',
            'secret-token',
            false,
            $keepTags,
            $teamId,
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

    private function seedProjectWithRegistry(RegistryIdentifier $registryId): Project
    {
        $server = $this->seedServer();
        $now    = TimestampImmutable::now();

        $project = new Project(
            ProjectIdentifier::create(),
            'Test Project',
            $server->id,
            $this->team->id,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
            $registryId,
        );

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);
        $repository->create($project);

        return $project;
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

    private function loginAndGetCookie(): string
    {
        $response = $this->dispatch('POST', $this->url('login'), [
            'email'    => self::EMAIL,
            'password' => self::PASSWORD,
        ]);

        return $this->getSessionCookie($response);
    }
}
