<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\EventListener\Environment;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Application\EventListener\Environment\QueueDockerCleanup;
use Tragwerk\Application\Queue\Message\CleanupEnvironmentDocker;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Enum\CredentialPrivilege;
use Tragwerk\Domain\Event\EnvironmentDeleted;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\CredentialIdentifier;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\IntegrationTestCase;
use TragwerkTest\Integration\Support\RecordingProducer;

use function assert;

final class QueueDockerCleanupTest extends IntegrationTestCase
{
    private UserIdentifier $userId;
    private TeamIdentifier $teamId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = UserIdentifier::create();
        $this->teamId = TeamIdentifier::create();

        $now = TimestampImmutable::now();

        $users = $this->container->get(UserRepository::class);
        assert($users instanceof UserRepository);
        $users->create(new User(
            $this->userId,
            'env-cleanup-test@example.com',
            'Env',
            'Cleanup',
            PasswordHash::create('password'),
            $now,
            $now,
        ));

        $teams = $this->container->get(TeamRepository::class);
        assert($teams instanceof TeamRepository);
        $teams->create(new Team($this->teamId, 'Team', $this->userId, $now, $this->userId, $now, $this->userId));
    }

    #[Test]
    public function enqueuesCleanupForServerWithCredential(): void
    {
        $credentialId = $this->seedCredential();
        $project      = $this->seedProject($this->seedServer($credentialId, '203.0.113.5', 2222));

        $producer = new RecordingProducer();
        $listener = new QueueDockerCleanup($this->projectRepository(), $this->serverRepository(), $producer);

        $listener(new EnvironmentDeleted($project->id, 'feature/login'));

        self::assertCount(1, $producer->messages);
        $message = $producer->messages[0];
        self::assertInstanceOf(CleanupEnvironmentDocker::class, $message);
        self::assertSame($project->id->toString(), $message->projectId);
        self::assertSame('feature/login', $message->branch);
        self::assertSame('203.0.113.5', $message->host);
        self::assertSame(2222, $message->port);
        self::assertSame($credentialId->toString(), $message->credentialId);
    }

    #[Test]
    public function doesNotEnqueueWhenServerHasNoCredential(): void
    {
        $project = $this->seedProject($this->seedServer(null, '203.0.113.6', 22));

        $producer = new RecordingProducer();
        $listener = new QueueDockerCleanup($this->projectRepository(), $this->serverRepository(), $producer);

        $listener(new EnvironmentDeleted($project->id, 'main'));

        self::assertSame([], $producer->messages);
    }

    private function projectRepository(): ProjectRepository
    {
        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);

        return $repository;
    }

    private function serverRepository(): ServerRepository
    {
        $repository = $this->container->get(ServerRepository::class);
        assert($repository instanceof ServerRepository);

        return $repository;
    }

    private function seedCredential(): CredentialIdentifier
    {
        $now          = TimestampImmutable::now();
        $credentialId = CredentialIdentifier::create();

        $credentials = $this->container->get(CredentialRepository::class);
        assert($credentials instanceof CredentialRepository);
        $credentials->create(new Credential(
            $credentialId,
            'Deploy key',
            'deploy',
            CredentialPrivilege::Root,
            'private-key',
            $this->teamId,
            $now,
            $this->userId,
            $now,
            $this->userId,
        ));

        return $credentialId;
    }

    private function seedServer(CredentialIdentifier|null $credentialId, string $host, int $port): ServerIdentifier
    {
        $now      = TimestampImmutable::now();
        $serverId = ServerIdentifier::create();

        $servers = $this->container->get(ServerRepository::class);
        assert($servers instanceof ServerRepository);
        $servers->create(new Server(
            $serverId,
            'Server',
            $host,
            $credentialId,
            $this->teamId,
            $now,
            $this->userId,
            $now,
            $this->userId,
            $port,
        ));

        return $serverId;
    }

    private function seedProject(ServerIdentifier $serverId): Project
    {
        $now        = TimestampImmutable::now();
        $registryId = RegistryIdentifier::create();

        $registries = $this->container->get(RegistryRepository::class);
        assert($registries instanceof RegistryRepository);
        $registries->create(new Registry(
            $registryId,
            'Reg',
            'registry.example.com',
            'repo',
            'user',
            'pass',
            false,
            10,
            $this->teamId,
            $now,
            $this->userId,
            $now,
            $this->userId,
        ));

        $project  = new Project(
            ProjectIdentifier::create(),
            'Test Project',
            $serverId,
            $this->teamId,
            $now,
            $this->userId,
            $now,
            $this->userId,
            $registryId,
        );
        $projects = $this->container->get(ProjectRepository::class);
        assert($projects instanceof ProjectRepository);
        $projects->create($project);

        return $project;
    }
}
