<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Team;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Application\Service\Registration\RegistrationSettings;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\TeamInvitation;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\TeamInvitationRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TeamInvitationIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;

/**
 * Turning off public sign-up must not close the invitation path — it is the
 * only way a private instance grows beyond its CLI-created accounts.
 */
final class InviteRegisterWithDisabledRegistrationTest extends AppIntegrationTestCase
{
    private const string TOKEN = '9f1c2a6e-3d4b-4c5a-8e7f-0a1b2c3d4e5f';

    private TeamInvitation $invitation;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = $this->seedUser('owner@example.com');
        $team  = $this->seedTeam($owner);

        $this->invitation = new TeamInvitation(
            TeamInvitationIdentifier::create(),
            $team->id,
            'invited@example.com',
            self::TOKEN,
            TimestampImmutable::now(),
            $owner->id,
        );
        $this->invitationRepository()->create($this->invitation);

        $this->container->setAllowOverride(true);
        $this->container->setService(RegistrationSettings::class, new RegistrationSettings(false));
        $this->container->setAllowOverride(false);
    }

    #[Test]
    public function inviteFormStaysReachable(): void
    {
        $response = $this->dispatch('GET', $this->url('team.invite', ['token' => self::TOKEN]));

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function invitedUserCanStillComplete(): void
    {
        $response = $this->dispatch('POST', $this->url('team.invite', ['token' => self::TOKEN]), [
            'firstname' => 'Max',
            'lastname'  => 'Mustermann',
            'password1' => 'secure-password-123',
            'password2' => 'secure-password-123',
        ]);

        self::assertSame(302, $response->getStatusCode());

        $repository = $this->userRepository();
        $user       = $repository->getAll(emails: ['invited@example.com'])->current();

        self::assertInstanceOf(User::class, $user);
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

        $repository = $this->userRepository();
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

        $repository = $this->container->get(TeamRepository::class);
        assert($repository instanceof TeamRepository);
        $repository->create($team);

        return $team;
    }
}
