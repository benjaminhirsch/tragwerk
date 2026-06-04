<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Project;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Application\Handler\Project\DownloadBuildHandler;
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
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class DownloadBuildHandlerTest extends AppIntegrationTestCase
{
    private const string EMAIL    = 'download-test@example.com';
    private const string PASSWORD = 'secure-password-123';

    private string $tempDataDir;
    private User $user;
    private Team $team;
    private Server $server;
    private Project $project;
    private string $sessionCookie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDataDir = sys_get_temp_dir() . '/tw-test-data-' . uniqid();
        mkdir($this->tempDataDir, 0755, true);

        $projects = $this->container->get(ProjectRepository::class);
        assert($projects instanceof ProjectRepository);

        $this->container->setAllowOverride(true);
        $this->container->setService(
            DownloadBuildHandler::class,
            new DownloadBuildHandler($projects, $this->tempDataDir),
        );
        $this->container->setAllowOverride(false);

        $this->user          = $this->seedUser();
        $this->team          = $this->seedTeam($this->user);
        $this->server        = $this->seedServer($this->user, $this->team);
        $this->project       = $this->seedProject($this->user, $this->server, $this->team);
        $this->sessionCookie = $this->loginAndGetCookie();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDataDir);

        parent::tearDown();
    }

    #[Test]
    public function redirectsUnauthenticatedUser(): void
    {
        $url      = $this->downloadUrl('main');
        $response = $this->dispatch('GET', $url);

        self::assertSame(302, $response->getStatusCode());
    }

    #[Test]
    public function returnsBadRequestWhenBranchIsMissing(): void
    {
        $url      = $this->url('project.environment.download', ['id' => $this->project->id->toString()]);
        $response = $this->dispatch('GET', $url, cookie: $this->sessionCookie);

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function returnsNotFoundForUnknownProject(): void
    {
        $url      = $this->url('project.environment.download', ['id' => ProjectIdentifier::create()->toString()])
            . '?branch=main';
        $response = $this->dispatch('GET', $url, cookie: $this->sessionCookie);

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function returnsNotFoundForProjectFromOtherTeam(): void
    {
        $otherTeam   = $this->seedOtherTeam($this->user);
        $otherServer = $this->seedServer($this->user, $otherTeam, '10.0.0.2');
        $foreign     = $this->seedProjectForTeam($this->user, $otherServer, $otherTeam);

        $url      = $this->url('project.environment.download', ['id' => $foreign->id->toString()]) . '?branch=main';
        $response = $this->dispatch('GET', $url, cookie: $this->sessionCookie);

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function returnsNotFoundWhenZipDoesNotExist(): void
    {
        $response = $this->dispatch('GET', $this->downloadUrl('main'), cookie: $this->sessionCookie);

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function returnsZipFileWhenBuildExists(): void
    {
        $this->createFakeBuildZip($this->project->id->toString(), 'main');

        $response = $this->dispatch('GET', $this->downloadUrl('main'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/zip', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('attachment', $response->getHeaderLine('Content-Disposition'));
        self::assertStringContainsString('build.zip', $response->getHeaderLine('Content-Disposition'));
    }

    #[Test]
    public function returnsZipForBranchWithSlashInName(): void
    {
        $this->createFakeBuildZip($this->project->id->toString(), 'feature/my-feature');

        $response = $this->dispatch('GET', $this->downloadUrl('feature/my-feature'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    private function downloadUrl(string $branch): string
    {
        return $this->url('project.environment.download', ['id' => $this->project->id->toString()])
            . '?branch=' . $branch;
    }

    private function createFakeBuildZip(string $projectId, string $branch): void
    {
        $dir = $this->tempDataDir . '/' . $projectId . '/' . $branch;
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/build.zip', 'PK');
    }

    private function seedUser(): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            self::EMAIL,
            'Download',
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

    private function seedTeam(User $user): Team
    {
        $now  = TimestampImmutable::now();
        $team = new Team(
            TeamIdentifier::create(),
            'Download Test Team',
            $user->id,
            $now,
            $user->id,
            $now,
            $user->id,
        );

        $repository = $this->container->get(TeamRepository::class);
        assert($repository instanceof TeamRepository);
        $repository->create($team);
        $repository->assignUsers($team->id, [$user->id]);

        return $team;
    }

    private function seedOtherTeam(User $user): Team
    {
        $now  = TimestampImmutable::now();
        $team = new Team(
            TeamIdentifier::create(),
            'Other Team',
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

    private function seedServer(User $user, Team $team, string $ip = '10.0.0.1'): Server
    {
        $now    = TimestampImmutable::now();
        $server = new Server(
            ServerIdentifier::create(),
            'Download Test Server',
            $ip,
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

    private function seedProject(User $user, Server $server, Team $team): Project
    {
        return $this->seedProjectForTeam($user, $server, $team);
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

    private function seedProjectForTeam(
        User $user,
        Server $server,
        Team $team,
        string $name = 'Download Test Project',
    ): Project {
        $now     = TimestampImmutable::now();
        $project = new Project(
            ProjectIdentifier::create(),
            $name,
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

    private function loginAndGetCookie(): string
    {
        $response = $this->dispatch('POST', $this->url('login'), [
            'email'    => self::EMAIL,
            'password' => self::PASSWORD,
        ]);

        return $this->getSessionCookie($response);
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path . '/' . $entry;
            if (is_dir($full)) {
                $this->removeDirectory($full);
            } else {
                unlink($full);
            }
        }

        rmdir($path);
    }
}
