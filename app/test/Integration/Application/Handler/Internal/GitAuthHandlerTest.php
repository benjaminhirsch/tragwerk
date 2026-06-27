<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Internal;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\SshKey;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Enum\TeamRole;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Repository\SshKeyRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\SshKeyIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;

final class GitAuthHandlerTest extends AppIntegrationTestCase
{
    private const string PUBLIC_KEY = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIM1N key@test';

    private Project $projectA;
    private Project $projectB;
    private SshKey $ownerKey;
    private SshKey $adminKey;
    private SshKey $memberKey;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Team A: owner + admin + member, all with their own SSH key.
        $owner  = $this->seedUser('owner@example.com');
        $admin  = $this->seedUser('admin@example.com');
        $member = $this->seedUser('member@example.com');
        $teamA  = $this->seedTeam($owner, 'Team A');
        $this->assignRole($teamA, $owner, TeamRole::Owner);
        $this->assignRole($teamA, $admin, TeamRole::Admin);
        $this->assignRole($teamA, $member, TeamRole::Member);
        $this->projectA = $this->seedProject($owner, $teamA);

        $this->ownerKey  = $this->seedKey($owner);
        $this->adminKey  = $this->seedKey($admin);
        $this->memberKey = $this->seedKey($member);

        // Team B: separate tenant. None of team A's users are members.
        $foreign = $this->seedUser('foreign@example.com');
        $teamB   = $this->seedTeam($foreign, 'Team B');
        $this->assignRole($teamB, $foreign, TeamRole::Owner);
        $this->projectB = $this->seedProject($foreign, $teamB);
    }

    #[Test]
    public function ownerCanRead(): void
    {
        self::assertSame(200, $this->authorize($this->ownerKey, $this->projectA, 'read'));
    }

    #[Test]
    public function ownerCanWrite(): void
    {
        self::assertSame(200, $this->authorize($this->ownerKey, $this->projectA, 'write'));
    }

    #[Test]
    public function adminCanWrite(): void
    {
        self::assertSame(200, $this->authorize($this->adminKey, $this->projectA, 'write'));
    }

    #[Test]
    public function memberCanReadAndWrite(): void
    {
        self::assertSame(200, $this->authorize($this->memberKey, $this->projectA, 'read'));
        self::assertSame(200, $this->authorize($this->memberKey, $this->projectA, 'write'));
    }

    #[Test]
    public function nonMemberDeniedRead(): void
    {
        self::assertSame(403, $this->authorize($this->ownerKey, $this->projectB, 'read'));
    }

    #[Test]
    public function nonMemberDeniedWrite(): void
    {
        self::assertSame(403, $this->authorize($this->ownerKey, $this->projectB, 'write'));
    }

    #[Test]
    public function unknownProjectDenied(): void
    {
        $response = $this->dispatch('POST', $this->url('internal.git-auth'), [
            'keyId'     => $this->ownerKey->id->toString(),
            'projectId' => ProjectIdentifier::create()->toString(),
            'op'        => 'read',
        ]);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function unknownKeyDenied(): void
    {
        $response = $this->dispatch('POST', $this->url('internal.git-auth'), [
            'keyId'     => SshKeyIdentifier::create()->toString(),
            'projectId' => $this->projectA->id->toString(),
            'op'        => 'read',
        ]);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function invalidProjectIdFormatReturns400(): void
    {
        $response = $this->dispatch('POST', $this->url('internal.git-auth'), [
            'keyId'     => $this->ownerKey->id->toString(),
            'projectId' => 'repos/../etc',
            'op'        => 'read',
        ]);

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function invalidOpReturns400(): void
    {
        $response = $this->dispatch('POST', $this->url('internal.git-auth'), [
            'keyId'     => $this->ownerKey->id->toString(),
            'projectId' => $this->projectA->id->toString(),
            'op'        => 'exec',
        ]);

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function missingBodyReturns400(): void
    {
        $response = $this->dispatch('POST', $this->url('internal.git-auth'));

        self::assertSame(400, $response->getStatusCode());
    }

    private function authorize(SshKey $key, Project $project, string $op): int
    {
        return $this->dispatch('POST', $this->url('internal.git-auth'), [
            'keyId'     => $key->id->toString(),
            'projectId' => $project->id->toString(),
            'op'        => $op,
        ])->getStatusCode();
    }

    private function seedUser(string $email): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            $email,
            'Git',
            'Tester',
            PasswordHash::create('password123'),
            $now,
            $now,
        );

        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);
        $repository->create($user);

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

        $repository = $this->container->get(TeamRepository::class);
        assert($repository instanceof TeamRepository);
        $repository->create($team);

        return $team;
    }

    private function assignRole(Team $team, User $user, TeamRole $role): void
    {
        $repository = $this->container->get(TeamRepository::class);
        assert($repository instanceof TeamRepository);
        $repository->assignUsers($team->id, [$user->id], $role);
    }

    private function seedServer(User $user, Team $team): Server
    {
        $seq    = ++$this->seq;
        $now    = TimestampImmutable::now();
        $server = new Server(
            ServerIdentifier::create(),
            'Git Test Server ' . $seq,
            '10.0.0.' . $seq,
            null,
            $team->id,
            $now,
            $user->id,
            $now,
            $user->id,
        );

        $repository = $this->container->get(ServerRepository::class);
        assert($repository instanceof ServerRepository);
        $repository->create($server);

        return $server;
    }

    private function seedRegistry(User $user, Team $team): RegistryIdentifier
    {
        $seq      = ++$this->seq;
        $now      = TimestampImmutable::now();
        $registry = new Registry(
            RegistryIdentifier::create(),
            'Test Registry ' . $seq,
            'registry' . $seq . '.example.com',
            'test-repo',
            'user',
            'pass',
            false,
            10,
            $team->id,
            $now,
            $user->id,
            $now,
            $user->id,
        );

        $repository = $this->container->get(RegistryRepository::class);
        assert($repository instanceof RegistryRepository);
        $repository->create($registry);

        return $registry->id;
    }

    private function seedProject(User $user, Team $team): Project
    {
        $server  = $this->seedServer($user, $team);
        $now     = TimestampImmutable::now();
        $project = new Project(
            ProjectIdentifier::create(),
            'Git Test Project',
            $server->id,
            $team->id,
            $now,
            $user->id,
            $now,
            $user->id,
            $this->seedRegistry($user, $team),
        );

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);
        $repository->create($project);

        return $project;
    }

    private function seedKey(User $user): SshKey
    {
        $key = new SshKey(
            SshKeyIdentifier::create(),
            $user->id,
            'test-key',
            self::PUBLIC_KEY,
            TimestampImmutable::now(),
        );

        $repository = $this->container->get(SshKeyRepository::class);
        assert($repository instanceof SshKeyRepository);
        $repository->create($key);

        return $key;
    }
}
