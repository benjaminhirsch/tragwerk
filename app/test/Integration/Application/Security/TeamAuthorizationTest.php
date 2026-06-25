<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Security;

use Mezzio\Authentication\DefaultUser;
use Mezzio\Authentication\UserInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use Tragwerk\Application\Security\TeamAuthorization;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Enum\TeamPermission;
use Tragwerk\Domain\Enum\TeamRole;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\IntegrationTestCase;

use function assert;

final class TeamAuthorizationTest extends IntegrationTestCase
{
    private TeamAuthorization $authorization;
    private TeamRepository $teamRepository;
    private Team $team;
    private User $owner;
    private User $admin;
    private User $member;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->container->get(TeamRepository::class);
        assert($repository instanceof TeamRepository);
        $this->teamRepository = $repository;
        $this->authorization  = new TeamAuthorization($repository);

        $this->owner    = $this->seedUser('owner@example.com');
        $this->admin    = $this->seedUser('admin@example.com');
        $this->member   = $this->seedUser('member@example.com');
        $this->outsider = $this->seedUser('outsider@example.com');

        $this->team = $this->seedTeam($this->owner);
        $this->teamRepository->assignUsers($this->team->id, [$this->owner->id], TeamRole::Owner);
        $this->teamRepository->assignUsers($this->team->id, [$this->admin->id], TeamRole::Admin);
        $this->teamRepository->assignUsers($this->team->id, [$this->member->id], TeamRole::Member);
    }

    #[Test]
    public function ownerIsGrantedEveryPermission(): void
    {
        foreach (TeamPermission::cases() as $permission) {
            self::assertTrue(
                $this->authorization->isGranted($permission->value, $this->requestFor($this->owner)),
                $permission->value,
            );
        }
    }

    #[Test]
    public function adminMayManageButNotDelete(): void
    {
        $request = $this->requestFor($this->admin);

        self::assertTrue($this->authorization->isGranted(TeamPermission::EditTeam->value, $request));
        self::assertTrue($this->authorization->isGranted(TeamPermission::ManageMembers->value, $request));
        self::assertFalse($this->authorization->isGranted(TeamPermission::DeleteTeam->value, $request));
    }

    #[Test]
    public function memberMayOnlyView(): void
    {
        $request = $this->requestFor($this->member);

        self::assertTrue($this->authorization->isGranted(TeamPermission::ViewTeam->value, $request));
        self::assertFalse($this->authorization->isGranted(TeamPermission::EditTeam->value, $request));
        self::assertFalse($this->authorization->isGranted(TeamPermission::ManageMembers->value, $request));
    }

    #[Test]
    public function nonMemberIsDeniedEverything(): void
    {
        $request = $this->requestFor($this->outsider);

        foreach (TeamPermission::cases() as $permission) {
            self::assertFalse(
                $this->authorization->isGranted($permission->value, $request),
                $permission->value,
            );
        }
    }

    #[Test]
    public function unknownPermissionStringIsDenied(): void
    {
        self::assertFalse($this->authorization->isGranted('not-a-permission', $this->requestFor($this->owner)));
    }

    #[Test]
    public function missingTeamIdIsDenied(): void
    {
        $request = (new Psr17Factory())
            ->createServerRequest('GET', '/')
            ->withAttribute(UserInterface::class, new DefaultUser($this->owner->id->toString()));

        self::assertFalse($this->authorization->isGranted(TeamPermission::ViewTeam->value, $request));
    }

    private function requestFor(User $user): ServerRequestInterface
    {
        return (new Psr17Factory())
            ->createServerRequest('GET', '/')
            ->withAttribute(UserInterface::class, new DefaultUser($user->id->toString()))
            ->withAttribute('id', $this->team->id->toString());
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
