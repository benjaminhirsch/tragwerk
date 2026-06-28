<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\EventListener\Environment;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Application\EventListener\Environment\DeleteEnvironmentJobs;
use Tragwerk\Domain\Entity\DeployJob;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Enum\DeployJobStatus;
use Tragwerk\Domain\Event\EnvironmentDeleted;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
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
use TragwerkTest\Integration\Support\IntegrationTestCase;

use function assert;

final class DeleteEnvironmentJobsTest extends IntegrationTestCase
{
    private DeployJobRepository $repository;
    private ProjectIdentifier $projectId;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->container->get(DeployJobRepository::class);
        assert($repository instanceof DeployJobRepository);
        $this->repository = $repository;

        $this->projectId = $this->seedProject();
    }

    #[Test]
    public function deletesOnlyJobsOfTheGivenBranch(): void
    {
        $this->repository->create($this->job('feature'));
        $this->repository->create($this->job('feature'));
        $this->repository->create($this->job('main'));

        $listener = new DeleteEnvironmentJobs($this->repository);
        $listener(new EnvironmentDeleted($this->projectId, 'feature'));

        self::assertNull($this->repository->getLatestByProjectAndBranch($this->projectId, 'feature'));
        self::assertNotNull($this->repository->getLatestByProjectAndBranch($this->projectId, 'main'));
    }

    private function job(string $branch): DeployJob
    {
        $now = TimestampImmutable::now();

        return new DeployJob(
            DeployJobIdentifier::create(),
            $this->projectId,
            $branch,
            'abcdef1234567890abcdef1234567890abcdef12',
            DeployJobStatus::Completed,
            '',
            $now,
            $now,
        );
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
            'delete-env-jobs-test@example.com',
            'Delete',
            'Tester',
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
