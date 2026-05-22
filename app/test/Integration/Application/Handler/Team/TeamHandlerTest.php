<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Team;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;

final class TeamHandlerTest extends AppIntegrationTestCase
{
    private const string EMAIL    = 'team-test@example.com';
    private const string PASSWORD = 'secure-password-123';

    private User $user;
    private string $sessionCookie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user          = $this->seedUser();
        $this->sessionCookie = $this->loginAndGetCookie();
    }

    #[Test]
    public function listRendersTeamsPage(): void
    {
        $response = $this->dispatch('GET', $this->url('team'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function listRedirectsUnauthenticatedUserToLogin(): void
    {
        $response = $this->dispatch('GET', $this->url('team'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('login'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function createGetRendersForm(): void
    {
        $response = $this->dispatch('GET', $this->url('team.create'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithValidDataRedirectsToTeamList(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('team.create'),
            ['name' => 'My Test Team'],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('team'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function createPostPersistsTeamInDatabase(): void
    {
        $this->dispatch(
            'POST',
            $this->url('team.create'),
            ['name' => 'My Test Team'],
            $this->sessionCookie,
        );

        $repository = $this->container->get(TeamRepository::class);
        assert($repository instanceof TeamRepository);

        $teams = [...$repository->getByUserId($this->user->id)];
        self::assertCount(1, $teams);
        self::assertInstanceOf(Team::class, $teams[0]);
        self::assertSame('My Test Team', $teams[0]->name);
    }

    #[Test]
    public function createPostWithEmptyNameReRendersForm(): void
    {
        $response = $this->dispatch('POST', $this->url('team.create'), ['name' => ''], $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function editGetRendersFormWithTeam(): void
    {
        $team     = $this->seedTeam();
        $response = $this->dispatch(
            'GET',
            $this->url('team.edit', ['id' => $team->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function editPostWithValidDataRedirectsToTeamList(): void
    {
        $team     = $this->seedTeam();
        $response = $this->dispatch(
            'POST',
            $this->url('team.edit', ['id' => $team->id->toString()]),
            ['name' => 'Updated Team Name'],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('team'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function editPostUpdatesTeamNameInDatabase(): void
    {
        $team = $this->seedTeam();
        $this->dispatch(
            'POST',
            $this->url('team.edit', ['id' => $team->id->toString()]),
            ['name' => 'Updated Team Name'],
            $this->sessionCookie,
        );

        $repository = $this->container->get(TeamRepository::class);
        assert($repository instanceof TeamRepository);

        $updated = $repository->getById($team->id);
        assert($updated instanceof Team);
        self::assertSame('Updated Team Name', $updated->name);
    }

    #[Test]
    public function editPostWithEmptyNameReRendersForm(): void
    {
        $team     = $this->seedTeam();
        $response = $this->dispatch(
            'POST',
            $this->url('team.edit', ['id' => $team->id->toString()]),
            ['name' => ''],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function editGetWithUnknownTeamIdRedirectsToTeamList(): void
    {
        $response = $this->dispatch(
            'GET',
            $this->url('team.edit', ['id' => TeamIdentifier::create()->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('team'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function deletePostRedirectsToTeamList(): void
    {
        $team     = $this->seedTeam();
        $response = $this->dispatch(
            'POST',
            $this->url('team.delete', ['id' => $team->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('team'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function deletePostRemovesTeamFromDatabase(): void
    {
        $team = $this->seedTeam();
        $this->dispatch(
            'POST',
            $this->url('team.delete', ['id' => $team->id->toString()]),
            cookie: $this->sessionCookie,
        );

        $repository = $this->container->get(TeamRepository::class);
        assert($repository instanceof TeamRepository);

        $remaining = [...$repository->getByUserId($this->user->id)];
        self::assertCount(0, $remaining);
    }

    #[Test]
    public function deletePostWithUnknownTeamIdRedirectsToTeamList(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('team.delete', ['id' => TeamIdentifier::create()->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('team'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function showGetRendersDetailPage(): void
    {
        $team     = $this->seedTeam();
        $response = $this->dispatch(
            'GET',
            $this->url('team.show', ['id' => $team->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function showGetWithUnknownTeamIdRedirectsToTeamList(): void
    {
        $response = $this->dispatch(
            'GET',
            $this->url('team.show', ['id' => TeamIdentifier::create()->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('team'), $response->getHeaderLine('Location'));
    }

    private function seedUser(): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            self::EMAIL,
            'Team',
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

    private function loginAndGetCookie(): string
    {
        $response = $this->dispatch('POST', $this->url('login'), [
            'email'    => self::EMAIL,
            'password' => self::PASSWORD,
        ]);

        return $this->getSessionCookie($response);
    }
}
