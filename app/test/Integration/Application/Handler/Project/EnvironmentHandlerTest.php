<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Project;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\DeployJob;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Enum\DeployJobStatus;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\DeployJobIdentifier;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;

final class EnvironmentHandlerTest extends AppIntegrationTestCase
{
    private const string EMAIL    = 'environment-test@example.com';
    private const string PASSWORD = 'secure-password-123';
    private const string BRANCH   = 'main';

    private User $user;
    private Team $team;
    private Server $server;
    private string $sessionCookie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user          = $this->seedUser();
        $this->team          = $this->seedTeam();
        $this->server        = $this->seedServer();
        $this->sessionCookie = $this->loginAndGetCookie();
    }

    #[Test]
    public function tabEnvironmentsReturns200(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'GET',
            $this->url('project.show.tab', ['id' => $project->id->toString(), 'tab' => 'environments']),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function tabWithUnknownSlugReturns404(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'GET',
            $this->url('project.show.tab', ['id' => $project->id->toString(), 'tab' => 'nonexistent']),
            cookie: $this->sessionCookie,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function branchListReturns200(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'GET',
            $this->url('project.environment.branch-list', ['id' => $project->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function branchListWithUnknownProjectReturns404(): void
    {
        $response = $this->dispatch(
            'GET',
            $this->url('project.environment.branch-list', ['id' => ProjectIdentifier::create()->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function branchListWithProjectFromOtherTeamReturns404(): void
    {
        $foreign  = $this->seedProjectForTeam($this->seedOtherTeam()->id);
        $response = $this->dispatch(
            'GET',
            $this->url('project.environment.branch-list', ['id' => $foreign->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function environmentWithBranchReturns200(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'GET',
            $this->url('project.environment', ['id' => $project->id->toString()]) . '?branch=' . self::BRANCH,
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function environmentWithoutBranchReturns400(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'GET',
            $this->url('project.environment', ['id' => $project->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function environmentWithUnknownProjectReturns404(): void
    {
        $response = $this->dispatch(
            'GET',
            $this->url('project.environment', ['id' => ProjectIdentifier::create()->toString()])
            . '?branch=' . self::BRANCH,
            cookie: $this->sessionCookie,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function environmentWithProjectFromOtherTeamReturns404(): void
    {
        $foreign  = $this->seedProjectForTeam($this->seedOtherTeam()->id);
        $response = $this->dispatch(
            'GET',
            $this->url('project.environment', ['id' => $foreign->id->toString()]) . '?branch=' . self::BRANCH,
            cookie: $this->sessionCookie,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function deployLogWithBranchReturns200(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'GET',
            $this->url('project.environment.deploy-log', ['id' => $project->id->toString()])
            . '?branch=' . self::BRANCH,
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function deployLogWithoutBranchReturns400(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'GET',
            $this->url('project.environment.deploy-log', ['id' => $project->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function deployLogWithOffsetReturns200(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'GET',
            $this->url('project.environment.deploy-log', ['id' => $project->id->toString()])
            . '?branch=' . self::BRANCH . '&offset=20',
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function deployLogWithUnknownProjectReturns404(): void
    {
        $response = $this->dispatch(
            'GET',
            $this->url('project.environment.deploy-log', ['id' => ProjectIdentifier::create()->toString()])
            . '?branch=' . self::BRANCH,
            cookie: $this->sessionCookie,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function deployJobOutputReturns200(): void
    {
        $project  = $this->seedProject();
        $job      = $this->seedDeployJob($project);
        $response = $this->dispatch(
            'GET',
            $this->url('project.environment.deploy-job-output', [
                'id'    => $project->id->toString(),
                'jobId' => $job->id->toString(),
            ]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function deployJobOutputWithUnknownJobReturns404(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'GET',
            $this->url('project.environment.deploy-job-output', [
                'id'    => $project->id->toString(),
                'jobId' => DeployJobIdentifier::create()->toString(),
            ]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function deployJobOutputWithJobFromOtherProjectReturns404(): void
    {
        $project = $this->seedProject();
        $other   = $this->seedProjectForTeam($this->team->id, 'Other Project');
        $job     = $this->seedDeployJob($other);

        $response = $this->dispatch(
            'GET',
            $this->url('project.environment.deploy-job-output', [
                'id'    => $project->id->toString(),
                'jobId' => $job->id->toString(),
            ]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function redeployWithEmptyBranchReturns400(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'POST',
            $this->url('project.environment.redeploy', ['id' => $project->id->toString()]),
            ['branch' => ''],
            $this->sessionCookie,
        );

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function redeployWithNonExistentBranchReturns400(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'POST',
            $this->url('project.environment.redeploy', ['id' => $project->id->toString()]),
            ['branch' => 'no-such-branch'],
            $this->sessionCookie,
        );

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function redeployWithUnknownProjectReturns404(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('project.environment.redeploy', ['id' => ProjectIdentifier::create()->toString()]),
            ['branch' => self::BRANCH],
            $this->sessionCookie,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function syncDataWithEmptyBranchReturns400(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'POST',
            $this->url('project.environment.sync-data', ['id' => $project->id->toString()]),
            ['branch' => ''],
            $this->sessionCookie,
        );

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function syncDataWithNonExistentBranchReturns400(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'POST',
            $this->url('project.environment.sync-data', ['id' => $project->id->toString()]),
            ['branch' => 'no-such-branch'],
            $this->sessionCookie,
        );

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function syncDataWithUnknownProjectReturns404(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('project.environment.sync-data', ['id' => ProjectIdentifier::create()->toString()]),
            ['branch' => self::BRANCH],
            $this->sessionCookie,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    private function seedUser(): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            self::EMAIL,
            'Environment',
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

    private function seedServer(): Server
    {
        $now    = TimestampImmutable::now();
        $server = new Server(
            ServerIdentifier::create(),
            'Test Server',
            '10.0.0.1',
            null,
            $this->team->id,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
        );

        $repository = $this->container->get(ServerRepository::class);
        assert($repository instanceof ServerRepository);
        $repository->create($server);

        return $server;
    }

    private function seedRegistry(TeamIdentifier $teamId): RegistryIdentifier
    {
        $now      = TimestampImmutable::now();
        $registry = new Registry(
            RegistryIdentifier::create(),
            'Test Registry',
            'registry.example.com',
            'test-repo',
            'user',
            'pass',
            false,
            10,
            $teamId,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
        );

        $repository = $this->container->get(RegistryRepository::class);
        assert($repository instanceof RegistryRepository);
        $repository->create($registry);

        return $registry->id;
    }

    private function seedProject(string $name = 'Test Project'): Project
    {
        $now     = TimestampImmutable::now();
        $project = new Project(
            ProjectIdentifier::create(),
            $name,
            $this->server->id,
            $this->team->id,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
            $this->seedRegistry($this->team->id),
        );

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);
        $repository->create($project);

        return $project;
    }

    private function seedProjectForTeam(TeamIdentifier $teamId, string $name = 'Test Project'): Project
    {
        $now    = TimestampImmutable::now();
        $server = new Server(
            ServerIdentifier::create(),
            $name . ' Server',
            '10.0.1.1',
            null,
            $teamId,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
        );

        $serverRepo = $this->container->get(ServerRepository::class);
        assert($serverRepo instanceof ServerRepository);
        $serverRepo->create($server);

        $project = new Project(
            ProjectIdentifier::create(),
            $name,
            $server->id,
            $teamId,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
            $this->seedRegistry($teamId),
        );

        $projectRepo = $this->container->get(ProjectRepository::class);
        assert($projectRepo instanceof ProjectRepository);
        $projectRepo->create($project);

        return $project;
    }

    private function seedDeployJob(Project $project): DeployJob
    {
        $now = TimestampImmutable::now();
        $job = new DeployJob(
            id:        DeployJobIdentifier::create(),
            projectId: $project->id,
            branch:    self::BRANCH,
            commitSha: 'abc1234',
            status:    DeployJobStatus::Completed,
            output:    'Build successful',
            createdAt: $now,
            updatedAt: $now,
        );

        $repository = $this->container->get(DeployJobRepository::class);
        assert($repository instanceof DeployJobRepository);
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
