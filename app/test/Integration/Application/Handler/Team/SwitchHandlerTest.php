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

final class SwitchHandlerTest extends AppIntegrationTestCase
{
    private const string EMAIL    = 'switch-team-test@example.com';
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
    public function switchRedirectsToHomeWhenNoRefererProvided(): void
    {
        $team     = $this->seedTeam('Team A');
        $response = $this->dispatch(
            'POST',
            $this->url('team.switch'),
            ['teamId' => $team->id->toString()],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('home'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function switchWithRefererRedirectsBackToReferer(): void
    {
        $team     = $this->seedTeam('Team A');
        $response = $this->dispatch(
            'POST',
            $this->url('team.switch'),
            ['teamId' => $team->id->toString()],
            $this->sessionCookie,
            ['Referer' => '/some/page'],
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/some/page', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function switchWithValidTeamPersistsLastActiveTeamToDatabase(): void
    {
        $teamA = $this->seedTeam('Team A');
        $teamB = $this->seedTeam('Team B');

        $this->dispatch(
            'POST',
            $this->url('team.switch'),
            ['teamId' => $teamB->id->toString()],
            $this->sessionCookie,
        );

        $userRepository = $this->container->get(UserRepository::class);
        assert($userRepository instanceof UserRepository);

        $lastActive = $userRepository->getLastActiveTeamId($this->user->id);

        self::assertNotNull($lastActive);
        self::assertSame($teamB->id->toString(), $lastActive->toString());

        unset($teamA);
    }

    #[Test]
    public function switchToTeamNotBelongingToUserDoesNotPersistToDatabase(): void
    {
        $otherUser = $this->seedOtherUser();
        $otherTeam = $this->seedTeamForUser('Foreign Team', $otherUser);

        $this->dispatch(
            'POST',
            $this->url('team.switch'),
            ['teamId' => $otherTeam->id->toString()],
            $this->sessionCookie,
        );

        $userRepository = $this->container->get(UserRepository::class);
        assert($userRepository instanceof UserRepository);

        $lastActive = $userRepository->getLastActiveTeamId($this->user->id);

        self::assertNull($lastActive);
    }

    #[Test]
    public function switchWithInvalidUuidDoesNotCrash(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('team.switch'),
            ['teamId' => 'not-a-uuid'],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
    }

    private function seedUser(): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            self::EMAIL,
            'Switch',
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

    private function seedOtherUser(): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            'other-' . self::EMAIL,
            'Other',
            'User',
            PasswordHash::create(self::PASSWORD),
            $now,
            $now,
        );

        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);
        $repository->create($user);

        return $user;
    }

    private function seedTeam(string $name): Team
    {
        return $this->seedTeamForUser($name, $this->user);
    }

    private function seedTeamForUser(string $name, User $owner): Team
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

        $repository = $this->container->get(TeamRepository::class);
        assert($repository instanceof TeamRepository);
        $repository->create($team);
        $repository->assignUsers($team->id, [$owner->id]);

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
