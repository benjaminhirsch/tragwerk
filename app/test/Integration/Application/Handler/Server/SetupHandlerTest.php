<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Server;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\SetupJob;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Enum\SetupJobStatus;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Repository\SetupJobRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\SetupJobIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;

final class SetupHandlerTest extends AppIntegrationTestCase
{
    private const string EMAIL    = 'setup-test@example.com';
    private const string PASSWORD = 'secure-password-123';

    private User $user;
    private Team $team;
    private string $sessionCookie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user          = $this->seedUser();
        $this->team          = $this->seedTeam();
        $this->sessionCookie = $this->loginAndGetCookie();
    }

    #[Test]
    public function setupPostRedirectsToNewJobPage(): void
    {
        $server   = $this->seedServer('My Server', '10.0.0.1');
        $response = $this->dispatch(
            'POST',
            $this->url('server.setup.start', ['id' => $server->id->toString()]),
            cookie: $this->sessionCookie,
        );

        $location = $response->getHeaderLine('Location');
        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/servers/' . $server->id->toString(), $location);
        self::assertStringContainsString('#setup', $location);
    }

    #[Test]
    public function setupPostPersistsJobInDatabase(): void
    {
        $server = $this->seedServer('My Server', '10.0.0.1');
        $this->dispatch(
            'POST',
            $this->url('server.setup.start', ['id' => $server->id->toString()]),
            cookie: $this->sessionCookie,
        );

        $repository = $this->container->get(SetupJobRepository::class);
        assert($repository instanceof SetupJobRepository);

        $job = $repository->getLatestForServer($server->id);
        self::assertInstanceOf(SetupJob::class, $job);
        self::assertSame(SetupJobStatus::Pending, $job->status);
        self::assertSame($server->id->toString(), $job->serverId->toString());
    }

    #[Test]
    public function setupPostWithPendingJobRedirectsToServerShow(): void
    {
        $server = $this->seedServer('My Server', '10.0.0.1');
        $this->seedSetupJob($server, SetupJobStatus::Pending);

        $response = $this->dispatch(
            'POST',
            $this->url('server.setup.start', ['id' => $server->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/servers/' . $server->id->toString(), $response->getHeaderLine('Location'));
        self::assertStringContainsString('#setup', $response->getHeaderLine('Location'));

        $repository = $this->container->get(SetupJobRepository::class);
        assert($repository instanceof SetupJobRepository);
        $all = [
            ...$this->connection->executeQuery(
                'SELECT id FROM setup_jobs WHERE server_id = ?',
                [$server->id->toString()],
            )->iterateAssociative(),
        ];
        self::assertCount(1, $all);
    }

    #[Test]
    public function setupPostWithRunningJobRedirectsToServerShow(): void
    {
        $server = $this->seedServer('My Server', '10.0.0.1');
        $this->seedSetupJob($server, SetupJobStatus::Running);

        $response = $this->dispatch(
            'POST',
            $this->url('server.setup.start', ['id' => $server->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/servers/' . $server->id->toString(), $response->getHeaderLine('Location'));
        self::assertStringContainsString('#setup', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function setupPostWithCompletedJobCreatesNewJob(): void
    {
        $server = $this->seedServer('My Server', '10.0.0.1');
        $this->seedSetupJob($server, SetupJobStatus::Completed);

        $this->dispatch(
            'POST',
            $this->url('server.setup.start', ['id' => $server->id->toString()]),
            cookie: $this->sessionCookie,
        );

        $all = [
            ...$this->connection->executeQuery(
                'SELECT id FROM setup_jobs WHERE server_id = ?',
                [$server->id->toString()],
            )->iterateAssociative(),
        ];
        self::assertCount(2, $all);
    }

    #[Test]
    public function setupPostWithFailedJobCreatesNewJob(): void
    {
        $server = $this->seedServer('My Server', '10.0.0.1');
        $this->seedSetupJob($server, SetupJobStatus::Failed);

        $this->dispatch(
            'POST',
            $this->url('server.setup.start', ['id' => $server->id->toString()]),
            cookie: $this->sessionCookie,
        );

        $all = [
            ...$this->connection->executeQuery(
                'SELECT id FROM setup_jobs WHERE server_id = ?',
                [$server->id->toString()],
            )->iterateAssociative(),
        ];
        self::assertCount(2, $all);
    }

    #[Test]
    public function setupPostWithUnknownServerRedirectsToServerList(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('server.setup.start', ['id' => ServerIdentifier::create()->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('server'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function setupPostWithServerFromOtherTeamRedirectsToServerList(): void
    {
        $otherServer = $this->seedServerForTeam('Foreign', '10.10.10.10', $this->seedOtherTeam()->id);

        $response = $this->dispatch(
            'POST',
            $this->url('server.setup.start', ['id' => $otherServer->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('server'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function setupPostUnauthenticatedRedirectsToLogin(): void
    {
        $server   = $this->seedServer('My Server', '10.0.0.1');
        $response = $this->dispatch(
            'POST',
            $this->url('server.setup.start', ['id' => $server->id->toString()]),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('login'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function setupPageRendersJobPage(): void
    {
        $server   = $this->seedServer('My Server', '10.0.0.1');
        $job      = $this->seedSetupJob($server, SetupJobStatus::Pending);
        $response = $this->dispatch(
            'GET',
            $this->url('server.setup', ['id' => $server->id->toString(), 'jobId' => $job->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function setupPageWithUnknownJobRedirectsToServerList(): void
    {
        $server   = $this->seedServer('My Server', '10.0.0.1');
        $response = $this->dispatch(
            'GET',
            $this->url('server.setup', [
                'id'    => $server->id->toString(),
                'jobId' => SetupJobIdentifier::create()->toString(),
            ]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('server'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function setupPageWithJobFromOtherServerRedirectsToServerList(): void
    {
        $serverA = $this->seedServer('Server A', '10.0.0.1');
        $serverB = $this->seedServer('Server B', '10.0.0.2');
        $jobB    = $this->seedSetupJob($serverB, SetupJobStatus::Pending);

        $response = $this->dispatch(
            'GET',
            $this->url('server.setup', ['id' => $serverA->id->toString(), 'jobId' => $jobB->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('server'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function logWithInvalidJobReturnsHxRedirect(): void
    {
        $server   = $this->seedServer('My Server', '10.0.0.1');
        $response = $this->dispatch(
            'GET',
            $this->url('server.setup.log', [
                'id'    => $server->id->toString(),
                'jobId' => SetupJobIdentifier::create()->toString(),
            ]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($this->url('server'), $response->getHeaderLine('HX-Redirect'));
    }

    #[Test]
    public function logReturnsHtmlWithOutput(): void
    {
        $server = $this->seedServer('My Server', '10.0.0.1');
        $job    = $this->seedSetupJob($server, SetupJobStatus::Running, 'Some log output');

        $response = $this->dispatch(
            'GET',
            $this->url('server.setup.log', ['id' => $server->id->toString(), 'jobId' => $job->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Some log output', (string) $response->getBody());
    }

    #[Test]
    public function logWithRunningJobContainsPollingAttributes(): void
    {
        $server = $this->seedServer('My Server', '10.0.0.1');
        $job    = $this->seedSetupJob($server, SetupJobStatus::Running);

        $response = $this->dispatch(
            'GET',
            $this->url('server.setup.log', ['id' => $server->id->toString(), 'jobId' => $job->id->toString()]),
            cookie: $this->sessionCookie,
        );

        $body = (string) $response->getBody();
        self::assertStringContainsString('hx-get', $body);
        self::assertStringContainsString('every 1s', $body);
    }

    #[Test]
    public function logWithCompletedJobHasNoPollingAttributes(): void
    {
        $server = $this->seedServer('My Server', '10.0.0.1');
        $job    = $this->seedSetupJob($server, SetupJobStatus::Completed);

        $response = $this->dispatch(
            'GET',
            $this->url('server.setup.log', ['id' => $server->id->toString(), 'jobId' => $job->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertStringNotContainsString('hx-get', (string) $response->getBody());
    }

    #[Test]
    public function serverIndexShowsReadyToDeployForCompletedJob(): void
    {
        $server = $this->seedServer('My Server', '10.0.0.1');
        $this->seedSetupJob($server, SetupJobStatus::Completed);

        $completedIds = $this->container->get(SetupJobRepository::class);
        assert($completedIds instanceof SetupJobRepository);

        $ids = $completedIds->getCompletedServerIds([$server->id]);
        self::assertContains($server->id->toString(), $ids);
    }

    #[Test]
    public function serverIndexShowsNotReadyWithoutCompletedJob(): void
    {
        $server = $this->seedServer('My Server', '10.0.0.1');
        $this->seedSetupJob($server, SetupJobStatus::Failed);

        $repository = $this->container->get(SetupJobRepository::class);
        assert($repository instanceof SetupJobRepository);

        $ids = $repository->getCompletedServerIds([$server->id]);
        self::assertNotContains($server->id->toString(), $ids);
    }

    #[Test]
    public function getCompletedServerIdsIgnoresOtherStatuses(): void
    {
        $serverA = $this->seedServer('Server A', '10.0.0.1');
        $serverB = $this->seedServer('Server B', '10.0.0.2');
        $serverC = $this->seedServer('Server C', '10.0.0.3');
        $this->seedSetupJob($serverA, SetupJobStatus::Completed);
        $this->seedSetupJob($serverB, SetupJobStatus::Failed);
        $this->seedSetupJob($serverC, SetupJobStatus::Running);

        $repository = $this->container->get(SetupJobRepository::class);
        assert($repository instanceof SetupJobRepository);

        $ids = $repository->getCompletedServerIds([$serverA->id, $serverB->id, $serverC->id]);
        self::assertCount(1, $ids);
        self::assertContains($serverA->id->toString(), $ids);
    }

    private function seedUser(): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            self::EMAIL,
            'Setup',
            'Tester',
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

    private function seedTeam(): Team
    {
        $now  = TimestampImmutable::now();
        $team = new Team(
            TeamIdentifier::create(),
            'Test Team',
            $this->user->id,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
        );

        $repository = $this->container->get(TeamRepository::class);
        assert($repository instanceof TeamRepository);
        $repository->create($team);
        $repository->assignUsers($team->id, [$this->user->id]);

        return $team;
    }

    private function seedOtherTeam(): Team
    {
        $now  = TimestampImmutable::now();
        $team = new Team(
            TeamIdentifier::create(),
            'Other Team',
            $this->user->id,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
        );

        $repository = $this->container->get(TeamRepository::class);
        assert($repository instanceof TeamRepository);
        $repository->create($team);

        return $team;
    }

    private function seedServer(string $name, string $host): Server
    {
        return $this->seedServerForTeam($name, $host, $this->team->id);
    }

    private function seedServerForTeam(string $name, string $host, TeamIdentifier $teamId): Server
    {
        $now    = TimestampImmutable::now();
        $server = new Server(
            ServerIdentifier::create(),
            $name,
            $host,
            null,
            $teamId,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
            22,
        );

        $repository = $this->container->get(ServerRepository::class);
        assert($repository instanceof ServerRepository);
        $repository->create($server);

        return $server;
    }

    private function seedSetupJob(Server $server, SetupJobStatus $status, string $output = ''): SetupJob
    {
        $now = TimestampImmutable::now();
        $job = new SetupJob(
            SetupJobIdentifier::create(),
            $server->id,
            $status,
            $output,
            $now,
            $now,
        );

        $repository = $this->container->get(SetupJobRepository::class);
        assert($repository instanceof SetupJobRepository);
        $repository->create($job);

        return $job;
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
