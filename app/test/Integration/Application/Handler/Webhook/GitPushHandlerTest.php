<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Webhook;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\BuildLogRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;
use function iterator_to_array;

final class GitPushHandlerTest extends AppIntegrationTestCase
{
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $user   = $this->seedUser();
        $team   = $this->seedTeam($user);
        $server = $this->seedServer($user, $team);

        $this->project = $this->seedProject($user, $server, $team);
    }

    #[Test]
    public function missingBodyReturns400(): void
    {
        $response = $this->dispatch('POST', $this->url('webhook.git-push'));

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function missingProjectIdReturns400(): void
    {
        $response = $this->dispatch('POST', $this->url('webhook.git-push'), [
            'branch' => 'main',
            'newSha' => 'abc123',
        ]);

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function invalidProjectIdFormatReturns400(): void
    {
        $response = $this->dispatch('POST', $this->url('webhook.git-push'), [
            'projectId' => 'not-a-uuid',
            'branch'    => 'main',
            'newSha'    => 'abc123',
        ]);

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function missingBranchReturns400(): void
    {
        $response = $this->dispatch('POST', $this->url('webhook.git-push'), [
            'projectId' => $this->project->id->toString(),
            'newSha'    => 'abc123',
        ]);

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function missingNewShaReturns400(): void
    {
        $response = $this->dispatch('POST', $this->url('webhook.git-push'), [
            'projectId' => $this->project->id->toString(),
            'branch'    => 'main',
        ]);

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function unknownProjectIdReturns404(): void
    {
        $response = $this->dispatch('POST', $this->url('webhook.git-push'), [
            'projectId' => ProjectIdentifier::create()->toString(),
            'branch'    => 'main',
            'newSha'    => 'abc123',
        ]);

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function validPushReturns200(): void
    {
        $response = $this->dispatch('POST', $this->url('webhook.git-push'), [
            'projectId' => $this->project->id->toString(),
            'branch'    => 'main',
            'newSha'    => 'abc123',
        ]);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function validPushCreatesBuildLogEntry(): void
    {
        $this->dispatch('POST', $this->url('webhook.git-push'), [
            'projectId' => $this->project->id->toString(),
            'branch'    => 'main',
            'newSha'    => 'abc123',
        ]);

        $repository = $this->container->get(BuildLogRepository::class);
        assert($repository instanceof BuildLogRepository);

        $logs = iterator_to_array($repository->getLatestByProjectAndBranch($this->project->id, 'main'));

        self::assertCount(1, $logs);
        self::assertSame('main', $logs[0]->branch);
        self::assertSame('PUSH', $logs[0]->type->value);
    }

    #[Test]
    public function branchDeleteWithZeroShaReturns200(): void
    {
        $response = $this->dispatch('POST', $this->url('webhook.git-push'), [
            'projectId' => $this->project->id->toString(),
            'branch'    => 'feature',
            'newSha'    => '0000000000000000000000000000000000000000',
        ]);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function branchDeleteWithZeroShaDoesNotTriggerBuild(): void
    {
        $this->dispatch('POST', $this->url('webhook.git-push'), [
            'projectId' => $this->project->id->toString(),
            'branch'    => 'feature',
            'newSha'    => '0000000000000000000000000000000000000000',
        ]);

        $repository = $this->container->get(BuildLogRepository::class);
        assert($repository instanceof BuildLogRepository);

        $logs = iterator_to_array($repository->getLatestByProjectAndBranch($this->project->id, 'feature'));

        self::assertSame([], $logs);
    }

    #[Test]
    public function validPushWithNoConfigFileLogsSkipMessage(): void
    {
        $this->dispatch('POST', $this->url('webhook.git-push'), [
            'projectId' => $this->project->id->toString(),
            'branch'    => 'main',
            'newSha'    => 'abc123',
        ]);

        $repository = $this->container->get(BuildLogRepository::class);
        assert($repository instanceof BuildLogRepository);

        $logs = iterator_to_array($repository->getLatestByProjectAndBranch($this->project->id, 'main'));

        self::assertStringContainsString('skipping validation', $logs[0]->message);
    }

    private function seedUser(): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            'webhook-test@example.com',
            'Webhook',
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

    private function seedTeam(User $user): Team
    {
        $now  = TimestampImmutable::now();
        $team = new Team(
            TeamIdentifier::create(),
            'Webhook Test Team',
            $user->id,
            $now,
            $user->id,
            $now,
            $user->id,
        );

        $repository = $this->container->get(TeamRepository::class);
        assert($repository instanceof TeamRepository);
        $repository->create($team);

        return $team;
    }

    private function seedServer(User $user, Team $team): Server
    {
        $now    = TimestampImmutable::now();
        $server = new Server(
            ServerIdentifier::create(),
            'Webhook Test Server',
            '10.0.0.1',
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

    private function seedProject(User $user, Server $server, Team $team): Project
    {
        $now     = TimestampImmutable::now();
        $project = new Project(
            ProjectIdentifier::create(),
            'Webhook Test Project',
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
}
