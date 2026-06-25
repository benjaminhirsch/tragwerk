<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Team;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\TeamInvitation;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Enum\TeamRole;
use Tragwerk\Domain\Repository\TeamInvitationRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;

final class InviteRoleTest extends AppIntegrationTestCase
{
    private const string PASSWORD = 'secure-password-123';

    private TeamRepository $teamRepository;
    private User $owner;
    private Team $team;
    private string $ownerCookie;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->container->get(TeamRepository::class);
        assert($repository instanceof TeamRepository);
        $this->teamRepository = $repository;

        $this->owner = $this->seedUser('owner@example.com');
        $this->team  = $this->seedTeam($this->owner);
        $this->teamRepository->assignUsers($this->team->id, [$this->owner->id], TeamRole::Owner);

        $this->ownerCookie = $this->loginAs('owner@example.com');
    }

    #[Test]
    public function existingUserIsAddedWithChosenRole(): void
    {
        $bob = $this->seedUser('bob@example.com');

        $this->dispatch(
            'POST',
            $this->url('team.edit', ['id' => $this->team->id->toString()]),
            [
                'name'           => 'Test Team',
                'emailsToInvite' => ['bob@example.com'],
                'rolesToInvite'  => [TeamRole::Admin->value],
            ],
            $this->ownerCookie,
        );

        self::assertSame(TeamRole::Admin, $this->teamRepository->roleOf($this->team->id, $bob->id));
    }

    #[Test]
    public function newEmailInvitationStoresChosenRole(): void
    {
        $this->dispatch(
            'POST',
            $this->url('team.edit', ['id' => $this->team->id->toString()]),
            [
                'name'           => 'Test Team',
                'emailsToInvite' => ['fresh@example.com'],
                'rolesToInvite'  => [TeamRole::Admin->value],
            ],
            $this->ownerCookie,
        );

        $invitations = $this->invitationRepository()->getRecentByTeam($this->team->id, 10);
        $invitation  = $this->findByEmail($invitations, 'fresh@example.com');

        self::assertInstanceOf(TeamInvitation::class, $invitation);
        self::assertSame(TeamRole::Admin, $invitation->role);
    }

    #[Test]
    public function craftedOwnerRoleFallsBackToMember(): void
    {
        $bob = $this->seedUser('bob@example.com');

        $this->dispatch(
            'POST',
            $this->url('team.edit', ['id' => $this->team->id->toString()]),
            [
                'name'           => 'Test Team',
                'emailsToInvite' => ['bob@example.com'],
                'rolesToInvite'  => [TeamRole::Owner->value],
            ],
            $this->ownerCookie,
        );

        self::assertSame(TeamRole::Member, $this->teamRepository->roleOf($this->team->id, $bob->id));
    }

    /** @param TeamInvitation[] $invitations */
    private function findByEmail(array $invitations, string $email): TeamInvitation|null
    {
        foreach ($invitations as $invitation) {
            if ($invitation->email === $email) {
                return $invitation;
            }
        }

        return null;
    }

    private function invitationRepository(): TeamInvitationRepository
    {
        $repository = $this->container->get(TeamInvitationRepository::class);
        assert($repository instanceof TeamInvitationRepository);

        return $repository;
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
