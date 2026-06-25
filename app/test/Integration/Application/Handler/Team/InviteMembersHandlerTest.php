<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Team;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
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

final class InviteMembersHandlerTest extends AppIntegrationTestCase
{
    private const string PASSWORD = 'secure-password-123';

    private TeamRepository $teamRepository;
    private User $owner;
    private User $member;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->container->get(TeamRepository::class);
        assert($repository instanceof TeamRepository);
        $this->teamRepository = $repository;

        $this->owner  = $this->seedUser('owner@example.com');
        $this->member = $this->seedUser('member@example.com');

        $this->team = $this->seedTeam($this->owner);
        $this->teamRepository->assignUsers($this->team->id, [$this->owner->id], TeamRole::Owner);
        $this->teamRepository->assignUsers($this->team->id, [$this->member->id], TeamRole::Member);
    }

    #[Test]
    public function ownerAddsExistingUserViaDedicatedEndpoint(): void
    {
        $bob = $this->seedUser('bob@example.com');

        $response = $this->invite('owner@example.com', ['bob@example.com'], [TeamRole::Admin->value]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(TeamRole::Admin, $this->teamRepository->roleOf($this->team->id, $bob->id));
    }

    #[Test]
    public function newEmailBecomesPendingInvitation(): void
    {
        $this->invite('owner@example.com', ['fresh@example.com'], [TeamRole::Admin->value]);

        $invitations = $this->invitationRepository()->getRecentByTeam($this->team->id, 10);

        $found = false;
        foreach ($invitations as $invitation) {
            assert($invitation instanceof TeamInvitation);
            if ($invitation->email !== 'fresh@example.com') {
                continue;
            }

            $found = true;
            self::assertSame(TeamRole::Admin, $invitation->role);
        }

        self::assertTrue($found);
    }

    #[Test]
    public function memberCannotInvite(): void
    {
        $response = $this->invite('member@example.com', ['bob@example.com'], [TeamRole::Member->value]);

        self::assertSame(302, $response->getStatusCode());

        $bob = $this->userRepository()->searchByEmail('bob@example.com')->current();
        self::assertNull($bob);
    }

    /**
     * @param string[] $emails
     * @param string[] $roles
     */
    private function invite(string $actorEmail, array $emails, array $roles): ResponseInterface
    {
        return $this->dispatch(
            'POST',
            $this->url('team.members.invite', ['id' => $this->team->id->toString()]),
            ['emailsToInvite' => $emails, 'rolesToInvite' => $roles],
            $this->loginAs($actorEmail),
        );
    }

    private function invitationRepository(): TeamInvitationRepository
    {
        $repository = $this->container->get(TeamInvitationRepository::class);
        assert($repository instanceof TeamInvitationRepository);

        return $repository;
    }

    private function userRepository(): UserRepository
    {
        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);

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

        $this->userRepository()->create($user);
        $this->userRepository()->confirm($user->id);

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
