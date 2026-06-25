<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Infrastructure\Repository;

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
use TragwerkTest\Integration\Support\IntegrationTestCase;

use function assert;

final class TeamRepositoryRoleTest extends IntegrationTestCase
{
    private TeamRepository $teamRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->container->get(TeamRepository::class);
        assert($repository instanceof TeamRepository);
        $this->teamRepository = $repository;
    }

    #[Test]
    public function assignUsersStoresGivenRole(): void
    {
        $owner = $this->seedUser('owner@example.com');
        $team  = $this->seedTeam($owner);

        $this->teamRepository->assignUsers($team->id, [$owner->id], TeamRole::Owner);

        self::assertSame(TeamRole::Owner, $this->teamRepository->roleOf($team->id, $owner->id));
    }

    #[Test]
    public function assignUsersDefaultsToMember(): void
    {
        $owner  = $this->seedUser('owner@example.com');
        $member = $this->seedUser('member@example.com');
        $team   = $this->seedTeam($owner);

        $this->teamRepository->assignUsers($team->id, [$member->id]);

        self::assertSame(TeamRole::Member, $this->teamRepository->roleOf($team->id, $member->id));
    }

    #[Test]
    public function roleOfReturnsNullForNonMember(): void
    {
        $owner    = $this->seedUser('owner@example.com');
        $outsider = $this->seedUser('outsider@example.com');
        $team     = $this->seedTeam($owner);

        self::assertNull($this->teamRepository->roleOf($team->id, $outsider->id));
    }

    #[Test]
    public function getMembersWithRolesPairsUsersAndRoles(): void
    {
        $owner  = $this->seedUser('owner@example.com');
        $member = $this->seedUser('member@example.com');
        $team   = $this->seedTeam($owner);

        $this->teamRepository->assignUsers($team->id, [$owner->id], TeamRole::Owner);
        $this->teamRepository->assignUsers($team->id, [$member->id], TeamRole::Member);

        $memberships = $this->teamRepository->getMembersWithRoles($team->id);
        self::assertCount(2, $memberships);

        $byId = [];
        foreach ($memberships as $membership) {
            $byId[$membership->user->id->toString()] = $membership->role;
        }

        self::assertSame(TeamRole::Owner, $byId[$owner->id->toString()]);
        self::assertSame(TeamRole::Member, $byId[$member->id->toString()]);
    }

    private function seedUser(string $email): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            $email,
            'First',
            'Last',
            PasswordHash::create('secure-password-123'),
            $now,
            $now,
        );

        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);
        $repository->create($user);

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
