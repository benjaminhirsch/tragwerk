<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Infrastructure\Repository;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\Domain;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\DomainRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
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
use TragwerkTest\Integration\Support\IntegrationTestCase;

use function array_map;
use function assert;

final class DomainRepositoryTest extends IntegrationTestCase
{
    private DomainRepository $repository;
    private ProjectIdentifier $projectId;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->container->get(DomainRepository::class);
        assert($repository instanceof DomainRepository);
        $this->repository = $repository;

        $this->projectId = $this->seedProject();
    }

    #[Test]
    public function createPersistsAndFindByProjectReturnsAll(): void
    {
        $this->repository->create($this->makeDomain('example.com', isPrimary: true));
        $this->repository->create($this->makeDomain('preview.example.com', isWildcard: true));

        $domains = $this->repository->findByProject($this->projectId);

        self::assertCount(2, $domains);
        $hosts = array_map(static fn (Domain $d): string => $d->host, $domains);
        self::assertContains('example.com', $hosts);
        self::assertContains('preview.example.com', $hosts);
    }

    #[Test]
    public function isWildcardRoundTripsThroughDatabase(): void
    {
        $this->repository->create($this->makeDomain('preview.example.com', isWildcard: true));

        $domains = $this->repository->findByProject($this->projectId);

        self::assertCount(1, $domains);
        self::assertTrue($domains[0]->isWildcard);
    }

    #[Test]
    public function clearPrimaryUnsetsEveryPrimaryForProject(): void
    {
        $this->repository->create($this->makeDomain('a.com', isPrimary: true));
        $this->repository->create($this->makeDomain('b.com', isPrimary: false));

        $this->repository->clearPrimary($this->projectId);

        foreach ($this->repository->findByProject($this->projectId) as $domain) {
            self::assertFalse($domain->isPrimary);
        }
    }

    #[Test]
    public function setPrimaryMarksTheGivenDomain(): void
    {
        $domain = $this->makeDomain('a.com', isPrimary: false);
        $this->repository->create($domain);

        $this->repository->setPrimary($domain->id);

        $stored = $this->repository->findByProject($this->projectId);
        self::assertCount(1, $stored);
        self::assertTrue($stored[0]->isPrimary);
    }

    private function makeDomain(string $host, bool $isPrimary = false, bool $isWildcard = false): Domain
    {
        return new Domain(
            id:          DomainIdentifier::create(),
            projectId:   $this->projectId,
            host:        $host,
            isPrimary:   $isPrimary,
            createdAt:   TimestampImmutable::now(),
            placeholder: 'default',
            isWildcard:  $isWildcard,
        );
    }

    private function seedProject(): ProjectIdentifier
    {
        $now    = TimestampImmutable::now();
        $userId = UserIdentifier::create();
        $teamId = TeamIdentifier::create();

        $userRepository = $this->container->get(UserRepository::class);
        assert($userRepository instanceof UserRepository);
        $userRepository->create(new User(
            $userId,
            'domain-repo-test@example.com',
            'Domain',
            'Tester',
            PasswordHash::create('password'),
            $now,
            $now,
        ));

        $teamRepository = $this->container->get(TeamRepository::class);
        assert($teamRepository instanceof TeamRepository);
        $teamRepository->create(new Team($teamId, 'Test Team', $userId, $now, $userId, $now, $userId));

        $registry           = new Registry(
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
            $userId,
            $now,
            $userId,
        );
        $registryRepository = $this->container->get(RegistryRepository::class);
        assert($registryRepository instanceof RegistryRepository);
        $registryRepository->create($registry);

        $project           = new Project(
            ProjectIdentifier::create(),
            'Test Project',
            ServerIdentifier::create(),
            $teamId,
            $now,
            $userId,
            $now,
            $userId,
            $registry->id,
        );
        $projectRepository = $this->container->get(ProjectRepository::class);
        assert($projectRepository instanceof ProjectRepository);
        $projectRepository->create($project);

        return $project->id;
    }
}
