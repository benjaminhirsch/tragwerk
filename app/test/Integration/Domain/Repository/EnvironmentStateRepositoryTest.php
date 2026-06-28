<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Domain\Repository;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\EnvironmentStateRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\IntegrationTestCase;

use function assert;

final class EnvironmentStateRepositoryTest extends IntegrationTestCase
{
    private EnvironmentStateRepository $repository;
    private ProjectIdentifier $projectId;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->container->get(EnvironmentStateRepository::class);
        assert($repository instanceof EnvironmentStateRepository);
        $this->repository = $repository;

        $this->projectId = $this->seedProject();
    }

    #[Test]
    public function disableThenIsDisabledIsTrueAndEnableClearsIt(): void
    {
        self::assertFalse($this->repository->isDisabled($this->projectId, 'main'));

        $this->repository->disable($this->projectId, 'main');
        self::assertTrue($this->repository->isDisabled($this->projectId, 'main'));

        $this->repository->enable($this->projectId, 'main');
        self::assertFalse($this->repository->isDisabled($this->projectId, 'main'));
    }

    #[Test]
    public function disableIsIdempotent(): void
    {
        $this->repository->disable($this->projectId, 'main');
        $this->repository->disable($this->projectId, 'main');

        self::assertTrue($this->repository->isDisabled($this->projectId, 'main'));
    }

    #[Test]
    public function disabledMapReturnsOnlyDisabledBranches(): void
    {
        $this->repository->disable($this->projectId, 'feature');

        $map = $this->repository->disabledMap($this->projectId, ['main', 'feature']);

        self::assertSame(['feature' => true], $map);
    }

    private function seedProject(): ProjectIdentifier
    {
        $now    = TimestampImmutable::now();
        $userId = UserIdentifier::create();
        $teamId = TeamIdentifier::create();

        $users = $this->container->get(UserRepository::class);
        assert($users instanceof UserRepository);
        $users->create(new User(
            $userId,
            'env-state-test@example.com',
            'Env',
            'State',
            PasswordHash::create('password'),
            $now,
            $now,
        ));

        $teams = $this->container->get(TeamRepository::class);
        assert($teams instanceof TeamRepository);
        $teams->create(new Team($teamId, 'Test Team', $userId, $now, $userId, $now, $userId));

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
            $teamId,
            $now,
            $userId,
            $now,
            $userId,
        ));

        $projectId = ProjectIdentifier::create();
        $projects  = $this->container->get(ProjectRepository::class);
        assert($projects instanceof ProjectRepository);
        $projects->create(new Project(
            $projectId,
            'Test Project',
            ServerIdentifier::create(),
            $teamId,
            $now,
            $userId,
            $now,
            $userId,
            $registryId,
        ));

        return $projectId;
    }
}
