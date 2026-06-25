<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Team;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
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

final class RoleChangeHandlerTest extends AppIntegrationTestCase
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

        $this->team = $this->seedTeam($this->owner);
        $this->teamRepository->assignUsers($this->team->id, [$this->owner->id], TeamRole::Owner);
        $this->teamRepository->assignUsers($this->team->id, [$this->admin->id], TeamRole::Admin);
        $this->teamRepository->assignUsers($this->team->id, [$this->member->id], TeamRole::Member);
    }

    #[Test]
    public function adminPromotesMemberToAdmin(): void
    {
        $response = $this->changeRole('admin@example.com', $this->member->id, TeamRole::Admin);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(TeamRole::Admin, $this->teamRepository->roleOf($this->team->id, $this->member->id));
    }

    #[Test]
    public function adminCannotGrantOwner(): void
    {
        $this->changeRole('admin@example.com', $this->member->id, TeamRole::Owner);

        self::assertSame(TeamRole::Member, $this->teamRepository->roleOf($this->team->id, $this->member->id));
        self::assertSame($this->owner->id->toString(), $this->reloadTeam()->ownerId->toString());
    }

    #[Test]
    public function adminCannotChangeOwnersRole(): void
    {
        $this->changeRole('admin@example.com', $this->owner->id, TeamRole::Member);

        self::assertSame(TeamRole::Owner, $this->teamRepository->roleOf($this->team->id, $this->owner->id));
    }

    #[Test]
    public function memberCannotChangeRoles(): void
    {
        $response = $this->changeRole('member@example.com', $this->admin->id, TeamRole::Member);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(TeamRole::Admin, $this->teamRepository->roleOf($this->team->id, $this->admin->id));
    }

    #[Test]
    public function ownerTransfersOwnershipToAdmin(): void
    {
        $this->changeRole('owner@example.com', $this->admin->id, TeamRole::Owner);

        self::assertSame(TeamRole::Owner, $this->teamRepository->roleOf($this->team->id, $this->admin->id));
        self::assertSame(TeamRole::Admin, $this->teamRepository->roleOf($this->team->id, $this->owner->id));
        self::assertSame($this->admin->id->toString(), $this->reloadTeam()->ownerId->toString());
    }

    private function changeRole(string $actorEmail, UserIdentifier $target, TeamRole $role): ResponseInterface
    {
        return $this->dispatch(
            'POST',
            $this->url('team.members.role', ['id' => $this->team->id->toString()]),
            ['userId' => $target->toString(), 'role' => $role->value],
            $this->loginAs($actorEmail),
        );
    }

    private function reloadTeam(): Team
    {
        $team = $this->teamRepository->getById($this->team->id);
        assert($team instanceof Team);

        return $team;
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

    private function seedTeam(User $owner): Team
    {
        $now  = TimestampImmutable::now();
        $team = new Team(
            TeamIdentifier::create(),
            'Test Team',
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
