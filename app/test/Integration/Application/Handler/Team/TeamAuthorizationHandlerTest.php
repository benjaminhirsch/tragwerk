<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Team;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Enum\TeamRole;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;

final class TeamAuthorizationHandlerTest extends AppIntegrationTestCase
{
    private const string PASSWORD = 'secure-password-123';

    private TeamRepository $teamRepository;
    private User $owner;
    private User $admin;
    private User $member;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->container->get(TeamRepository::class);
        assert($repository instanceof TeamRepository);
        $this->teamRepository = $repository;

        $this->owner  = $this->seedUser('owner@example.com');
        $this->admin  = $this->seedUser('admin@example.com');
        $this->member = $this->seedUser('member@example.com');
        $this->seedUser('outsider@example.com');

        $this->team = $this->seedTeam($this->owner, 'Original Name');
        $this->teamRepository->assignUsers($this->team->id, [$this->owner->id], TeamRole::Owner);
        $this->teamRepository->assignUsers($this->team->id, [$this->admin->id], TeamRole::Admin);
        $this->teamRepository->assignUsers($this->team->id, [$this->member->id], TeamRole::Member);
    }

    #[Test]
    public function memberCannotOpenEditForm(): void
    {
        $response = $this->dispatch(
            'GET',
            $this->url('team.edit', ['id' => $this->team->id->toString()]),
            cookie: $this->loginAs('member@example.com'),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('team'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function memberCannotEditTeamName(): void
    {
        $this->dispatch(
            'POST',
            $this->url('team.edit', ['id' => $this->team->id->toString()]),
            ['name' => 'Hacked Name'],
            $this->loginAs('member@example.com'),
        );

        self::assertSame('Original Name', $this->currentTeamName());
    }

    #[Test]
    public function memberCannotDeleteTeam(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('team.delete', ['id' => $this->team->id->toString()]),
            cookie: $this->loginAs('member@example.com'),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('team'), $response->getHeaderLine('Location'));
        self::assertTrue($this->teamExists());
    }

    #[Test]
    public function memberCannotRemoveMembers(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('team.members.remove', ['id' => $this->team->id->toString()]),
            ['userId' => $this->admin->id->toString()],
            $this->loginAs('member@example.com'),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(TeamRole::Admin, $this->teamRepository->roleOf($this->team->id, $this->admin->id));
    }

    #[Test]
    public function adminCanEditTeamName(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('team.edit', ['id' => $this->team->id->toString()]),
            ['name' => 'Admin Renamed'],
            $this->loginAs('admin@example.com'),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('Admin Renamed', $this->currentTeamName());
    }

    #[Test]
    public function adminCannotDeleteTeam(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('team.delete', ['id' => $this->team->id->toString()]),
            cookie: $this->loginAs('admin@example.com'),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertTrue($this->teamExists());
    }

    #[Test]
    public function adminReachesRemoveMemberHandler(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('team.members.remove', ['id' => $this->team->id->toString()]),
            ['userId' => $this->member->id->toString()],
            $this->loginAs('admin@example.com'),
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function ownerCanDeleteTeamWhenNotLastTeam(): void
    {
        // Owner needs at least one other team for the "keep one team" rule to allow deletion.
        $second = $this->seedTeam($this->owner, 'Second Team');
        $this->teamRepository->assignUsers($second->id, [$this->owner->id], TeamRole::Owner);

        $response = $this->dispatch(
            'POST',
            $this->url('team.delete', ['id' => $this->team->id->toString()]),
            cookie: $this->loginAs('owner@example.com'),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertFalse($this->teamExists());
    }

    #[Test]
    public function outsiderCannotViewTeam(): void
    {
        $response = $this->dispatch(
            'GET',
            $this->url('team.show', ['id' => $this->team->id->toString()]),
            cookie: $this->loginAs('outsider@example.com'),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('team'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function memberCanViewTeam(): void
    {
        $response = $this->dispatch(
            'GET',
            $this->url('team.show', ['id' => $this->team->id->toString()]),
            cookie: $this->loginAs('member@example.com'),
        );

        self::assertSame(200, $response->getStatusCode());
    }

    private function currentTeamName(): string
    {
        $team = $this->teamRepository->getById($this->team->id);
        assert($team instanceof Team);

        return $team->name;
    }

    private function teamExists(): bool
    {
        $teams = [...$this->teamRepository->getByUserId($this->owner->id)];
        foreach ($teams as $team) {
            assert($team instanceof Team);
            if ($team->id->toString() === $this->team->id->toString()) {
                return true;
            }
        }

        return false;
    }

    private function loginAs(string $email): string
    {
        $response = $this->dispatch('POST', $this->url('login'), [
            'email'    => $email,
            'password' => self::PASSWORD,
        ]);

        return $this->getSessionCookie($response);
    }

    private function seedUser(string $email): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            $email,
            'First',
            'Last',
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

    private function seedTeam(User $owner, string $name): Team
    {
        $now  = TimestampImmutable::now();
        $team = new Team(
            TeamIdentifier::create(),
            $name,
            $owner->id,
            $now,
            $owner->id,
            $now,
            $owner->id,
        );

        $this->teamRepository->create($team);

        return $team;
    }
}
