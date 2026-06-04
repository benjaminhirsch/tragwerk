<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Project;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\Domain;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\DomainRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\DomainIdentifier;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use Tragwerk\Infrastructure\Dns\DnsResolver;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;
use function random_int;

final class DomainHandlerTest extends AppIntegrationTestCase
{
    private const string EMAIL     = 'domain-test@example.com';
    private const string PASSWORD  = 'secure-password-123';
    private const string SERVER_IP = '192.0.2.1';
    private const string BRANCH    = 'main';

    private User $user;
    private Team $team;
    private Server $server;
    private string $sessionCookie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container->setAllowOverride(true);
        $this->container->setService(DnsResolver::class, new readonly class (self::SERVER_IP) extends DnsResolver {
            public function __construct(private string $resolvedIp)
            {
            }

            public function toIpv4(string $host): string
            {
                return $this->resolvedIp;
            }
        });
        $this->container->setAllowOverride(false);

        $this->user          = $this->seedUser();
        $this->team          = $this->seedTeam();
        $this->server        = $this->seedServer();
        $this->sessionCookie = $this->loginAndGetCookie();
    }

    #[Test]
    public function addDomainWithValidHostReturns200(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'POST',
            $this->url('project.domain.add', ['id' => $project->id->toString(), 'branch' => self::BRANCH]),
            ['host' => 'example.com'],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function addDomainPersistsDomainInDatabase(): void
    {
        $project = $this->seedProject();
        $this->dispatch(
            'POST',
            $this->url('project.domain.add', ['id' => $project->id->toString(), 'branch' => self::BRANCH]),
            ['host' => 'example.com'],
            $this->sessionCookie,
        );

        $repository = $this->container->get(DomainRepository::class);
        assert($repository instanceof DomainRepository);

        $domains = $repository->findByEnvironment($project->id, self::BRANCH);
        self::assertCount(1, $domains);
        self::assertSame('example.com', $domains[0]->host);
    }

    #[Test]
    public function addFirstDomainBecomesThePrimary(): void
    {
        $project = $this->seedProject();
        $this->dispatch(
            'POST',
            $this->url('project.domain.add', ['id' => $project->id->toString(), 'branch' => self::BRANCH]),
            ['host' => 'example.com'],
            $this->sessionCookie,
        );

        $repository = $this->container->get(DomainRepository::class);
        assert($repository instanceof DomainRepository);

        $domains = $repository->findByEnvironment($project->id, self::BRANCH);
        self::assertTrue($domains[0]->isPrimary);
    }

    #[Test]
    public function addSecondDomainIsNotPrimary(): void
    {
        $project = $this->seedProject();
        $this->seedDomain($project, 'first.example.com');

        $this->dispatch(
            'POST',
            $this->url('project.domain.add', ['id' => $project->id->toString(), 'branch' => self::BRANCH]),
            ['host' => 'second.example.com'],
            $this->sessionCookie,
        );

        $repository = $this->container->get(DomainRepository::class);
        assert($repository instanceof DomainRepository);

        $domains = $repository->findByEnvironment($project->id, self::BRANCH);
        self::assertCount(2, $domains);
        $second = $domains[1]->host === 'second.example.com' ? $domains[1] : $domains[0];
        self::assertFalse($second->isPrimary);
    }

    #[Test]
    public function addDomainWithInvalidFormatDoesNotPersist(): void
    {
        $project = $this->seedProject();
        $this->dispatch(
            'POST',
            $this->url('project.domain.add', ['id' => $project->id->toString(), 'branch' => self::BRANCH]),
            ['host' => 'not a domain'],
            $this->sessionCookie,
        );

        $repository = $this->container->get(DomainRepository::class);
        assert($repository instanceof DomainRepository);

        self::assertSame([], $repository->findByEnvironment($project->id, self::BRANCH));
    }

    #[Test]
    public function addDuplicateDomainDoesNotPersist(): void
    {
        $project = $this->seedProject();
        $this->seedDomain($project, 'example.com');

        $this->dispatch(
            'POST',
            $this->url('project.domain.add', ['id' => $project->id->toString(), 'branch' => self::BRANCH]),
            ['host' => 'example.com'],
            $this->sessionCookie,
        );

        $repository = $this->container->get(DomainRepository::class);
        assert($repository instanceof DomainRepository);

        self::assertCount(1, $repository->findByEnvironment($project->id, self::BRANCH));
    }

    #[Test]
    public function addDomainWithUnresolvableHostDoesNotPersist(): void
    {
        $this->container->setAllowOverride(true);
        $this->container->setService(DnsResolver::class, new readonly class extends DnsResolver {
            public function toIpv4(string $host): string|null
            {
                return null;
            }
        });
        $this->container->setAllowOverride(false);

        $project = $this->seedProject();
        $this->dispatch(
            'POST',
            $this->url('project.domain.add', ['id' => $project->id->toString(), 'branch' => self::BRANCH]),
            ['host' => 'unresolvable.example.com'],
            $this->sessionCookie,
        );

        $repository = $this->container->get(DomainRepository::class);
        assert($repository instanceof DomainRepository);

        self::assertSame([], $repository->findByEnvironment($project->id, self::BRANCH));
    }

    #[Test]
    public function addDomainWithUnknownProjectReturns404(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('project.domain.add', [
                'id'     => ProjectIdentifier::create()->toString(),
                'branch' => self::BRANCH,
            ]),
            ['host' => 'example.com'],
            $this->sessionCookie,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function addDomainWithProjectFromOtherTeamReturns404(): void
    {
        $foreign  = $this->seedProjectForTeam($this->seedOtherTeam()->id);
        $response = $this->dispatch(
            'POST',
            $this->url('project.domain.add', ['id' => $foreign->id->toString(), 'branch' => self::BRANCH]),
            ['host' => 'example.com'],
            $this->sessionCookie,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function deleteDomainReturns200(): void
    {
        $project  = $this->seedProject();
        $domain   = $this->seedDomain($project, 'example.com');
        $response = $this->dispatch(
            'POST',
            $this->url('project.domain.delete', [
                'id'       => $project->id->toString(),
                'branch'   => self::BRANCH,
                'domainId' => $domain->id->toString(),
            ]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function deleteDomainRemovesDomainFromDatabase(): void
    {
        $project = $this->seedProject();
        $domain  = $this->seedDomain($project, 'example.com');

        $this->dispatch(
            'POST',
            $this->url('project.domain.delete', [
                'id'       => $project->id->toString(),
                'branch'   => self::BRANCH,
                'domainId' => $domain->id->toString(),
            ]),
            cookie: $this->sessionCookie,
        );

        $repository = $this->container->get(DomainRepository::class);
        assert($repository instanceof DomainRepository);

        self::assertSame([], $repository->findByEnvironment($project->id, self::BRANCH));
    }

    #[Test]
    public function deletePrimaryDomainPromotesNextDomainToPrimary(): void
    {
        $project   = $this->seedProject();
        $primary   = $this->seedDomain($project, 'primary.example.com', isPrimary: true);
        $secondary = $this->seedDomain($project, 'secondary.example.com', isPrimary: false);

        $this->dispatch(
            'POST',
            $this->url('project.domain.delete', [
                'id'       => $project->id->toString(),
                'branch'   => self::BRANCH,
                'domainId' => $primary->id->toString(),
            ]),
            cookie: $this->sessionCookie,
        );

        $repository = $this->container->get(DomainRepository::class);
        assert($repository instanceof DomainRepository);

        $remaining = $repository->findByEnvironment($project->id, self::BRANCH);
        self::assertCount(1, $remaining);
        self::assertSame($secondary->id->toString(), $remaining[0]->id->toString());
        self::assertTrue($remaining[0]->isPrimary);
    }

    #[Test]
    public function deleteDomainFromOtherProjectReturns404(): void
    {
        $project = $this->seedProject();
        $other   = $this->seedProjectForTeam($this->team->id, 'Other Project');
        $domain  = $this->seedDomain($other, 'example.com');

        $response = $this->dispatch(
            'POST',
            $this->url('project.domain.delete', [
                'id'       => $project->id->toString(),
                'branch'   => self::BRANCH,
                'domainId' => $domain->id->toString(),
            ]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function deleteDomainWithUnknownDomainIdReturns404(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'POST',
            $this->url('project.domain.delete', [
                'id'       => $project->id->toString(),
                'branch'   => self::BRANCH,
                'domainId' => DomainIdentifier::create()->toString(),
            ]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function setPrimaryDomainReturns200(): void
    {
        $project  = $this->seedProject();
        $domain   = $this->seedDomain($project, 'example.com', isPrimary: false);
        $response = $this->dispatch(
            'POST',
            $this->url('project.domain.primary', [
                'id'       => $project->id->toString(),
                'branch'   => self::BRANCH,
                'domainId' => $domain->id->toString(),
            ]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function setPrimaryDomainUpdatesPrimaryInDatabase(): void
    {
        $project   = $this->seedProject();
        $primary   = $this->seedDomain($project, 'primary.example.com', isPrimary: true);
        $secondary = $this->seedDomain($project, 'secondary.example.com', isPrimary: false);

        $this->dispatch(
            'POST',
            $this->url('project.domain.primary', [
                'id'       => $project->id->toString(),
                'branch'   => self::BRANCH,
                'domainId' => $secondary->id->toString(),
            ]),
            cookie: $this->sessionCookie,
        );

        $repository = $this->container->get(DomainRepository::class);
        assert($repository instanceof DomainRepository);

        $domains = $repository->findByEnvironment($project->id, self::BRANCH);
        self::assertCount(2, $domains);

        $byId = [];
        foreach ($domains as $d) {
            $byId[$d->id->toString()] = $d;
        }

        self::assertTrue($byId[$secondary->id->toString()]->isPrimary);
        self::assertFalse($byId[$primary->id->toString()]->isPrimary);
    }

    #[Test]
    public function setPrimaryDomainFromOtherProjectReturns404(): void
    {
        $project = $this->seedProject();
        $other   = $this->seedProjectForTeam($this->team->id, 'Other Project');
        $domain  = $this->seedDomain($other, 'example.com');

        $response = $this->dispatch(
            'POST',
            $this->url('project.domain.primary', [
                'id'       => $project->id->toString(),
                'branch'   => self::BRANCH,
                'domainId' => $domain->id->toString(),
            ]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    private function seedUser(): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            self::EMAIL,
            'Domain',
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
            self::SERVER_IP,
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
            '10.' . random_int(0, 254) . '.' . random_int(0, 254) . '.' . random_int(1, 254),
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

    private function seedDomain(
        Project $project,
        string $host,
        bool $isPrimary = true,
    ): Domain {
        $domain = new Domain(
            id:        DomainIdentifier::create(),
            projectId: $project->id,
            host:      $host,
            isPrimary: $isPrimary,
            createdAt: TimestampImmutable::now(),
            branch:    self::BRANCH,
        );

        $repository = $this->container->get(DomainRepository::class);
        assert($repository instanceof DomainRepository);
        $repository->create($domain);

        return $domain;
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
