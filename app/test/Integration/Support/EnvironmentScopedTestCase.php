<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Support;

use Psr\Http\Message\ResponseInterface;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
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
use Tragwerk\Infrastructure\Git\BareRepository;

use function assert;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function sys_get_temp_dir;
use function trim;
use function uniqid;
use function urlencode;

/**
 * Base case for environment-scoped handlers: seeds a confirmed user, team, server, registry and
 * project, provisions a git repository with a `main` branch, logs in, and activates both the project
 * and the environment so routes behind RequiresActiveProject/RequiresActiveEnvironment are reachable.
 */
abstract class EnvironmentScopedTestCase extends AppIntegrationTestCase
{
    protected const string EMAIL    = 'env-scoped-test@example.com';
    protected const string PASSWORD = 'secure-password-123';

    protected User $user;
    protected Team $team;
    protected Server $server;
    protected Registry $registry;
    protected Project $project;
    protected string $branch        = 'main';
    protected string $sessionCookie = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user     = $this->seedUser();
        $this->team     = $this->seedTeam();
        $this->server   = $this->seedServer();
        $this->registry = $this->seedRegistry();
        $this->project  = $this->seedProject();

        $bare = $this->container->get(BareRepository::class);
        assert($bare instanceof BareRepository);
        $bare->init($this->project->id->toString());
        $this->commitFile($bare->getPath($this->project->id->toString()), 'README.md', 'hello');

        $this->sessionCookie = $this->loginAndGetCookie();
        $this->activateProject();
        $this->activateEnvironment();
    }

    protected function activateProject(): void
    {
        $response = $this->dispatch(
            'GET',
            $this->url('project.show', ['id' => $this->project->id->toString()]),
            cookie: $this->sessionCookie,
        );
        $this->refreshSessionCookie($response);
    }

    protected function activateEnvironment(string|null $branch = null): void
    {
        $response = $this->dispatch(
            'GET',
            $this->url('environment.show') . '?id=' . urlencode($branch ?? $this->branch),
            cookie: $this->sessionCookie,
        );
        $this->refreshSessionCookie($response);
    }

    private function refreshSessionCookie(ResponseInterface $response): void
    {
        $cookie = $this->getSessionCookie($response);
        if ($cookie === '') {
            return;
        }

        $this->sessionCookie = $cookie;
    }

    private function seedUser(): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            self::EMAIL,
            'Env',
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

    private function seedRegistry(): Registry
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
            $this->team->id,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
        );

        $repository = $this->container->get(RegistryRepository::class);
        assert($repository instanceof RegistryRepository);
        $repository->create($registry);

        return $registry;
    }

    private function seedProject(): Project
    {
        $now     = TimestampImmutable::now();
        $project = new Project(
            ProjectIdentifier::create(),
            'Test Project',
            $this->server->id,
            $this->team->id,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
            $this->registry->id,
        );

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);
        $repository->create($project);

        return $project;
    }

    private function loginAndGetCookie(): string
    {
        $response = $this->dispatch('POST', $this->url('login'), [
            'email'    => self::EMAIL,
            'password' => self::PASSWORD,
        ]);

        return $this->getSessionCookie($response);
    }

    /** Commits a file on `main` of the project's bare repo so getBranches() returns it. */
    protected function commitFile(string $repoPath, string $filePath, string $content): string
    {
        $workDir = sys_get_temp_dir() . '/tw-work-' . uniqid();

        exec('git clone ' . escapeshellarg($repoPath) . ' ' . escapeshellarg($workDir) . ' 2>/dev/null');
        exec('git -C ' . escapeshellarg($workDir) . " config user.email 'test@test.com' 2>/dev/null");
        exec('git -C ' . escapeshellarg($workDir) . " config user.name 'Test' 2>/dev/null");

        $fullPath = $workDir . '/' . $filePath;
        $dir      = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($fullPath, $content);

        exec('git -C ' . escapeshellarg($workDir) . ' add . 2>/dev/null');
        exec('git -C ' . escapeshellarg($workDir) . " commit -m 'add file' 2>/dev/null");
        exec('git -C ' . escapeshellarg($workDir) . ' push origin HEAD:main 2>/dev/null');

        $sha = trim((string) exec('git -C ' . escapeshellarg($workDir) . ' rev-parse HEAD 2>/dev/null'));

        exec('rm -rf ' . escapeshellarg($workDir));

        return $sha;
    }
}
