<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Project;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\DeployJob;
use Tragwerk\Domain\Enum\DeployJobStatus;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\ValueObject\DeployJobIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Infrastructure\Git\BareRepository;
use TragwerkTest\Integration\Support\EnvironmentScopedTestCase;

use function assert;

final class DeleteEnvironmentHandlerTest extends EnvironmentScopedTestCase
{
    #[Test]
    public function deleteRemovesBranchAndDeployJobsAndRedirects(): void
    {
        $this->seedDeployJob($this->branch);

        $response = $this->dispatch(
            'POST',
            $this->url('project.environment.delete', ['id' => $this->project->id->toString()]),
            ['branch' => $this->branch],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(
            $this->url('project.show', ['id' => $this->project->id->toString()]),
            $response->getHeaderLine('Location'),
        );

        $bare = $this->container->get(BareRepository::class);
        assert($bare instanceof BareRepository);
        self::assertNotContains(
            $this->branch,
            $bare->getBranches($this->project->id->toString()),
        );

        $deployJobs = $this->container->get(DeployJobRepository::class);
        assert($deployJobs instanceof DeployJobRepository);
        self::assertNull($deployJobs->getLatestByProjectAndBranch($this->project->id, $this->branch));
    }

    #[Test]
    public function missingBranchReturns400(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('project.environment.delete', ['id' => $this->project->id->toString()]),
            [],
            $this->sessionCookie,
        );

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function unknownBranchReturns404(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('project.environment.delete', ['id' => $this->project->id->toString()]),
            ['branch' => 'does-not-exist'],
            $this->sessionCookie,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    private function seedDeployJob(string $branch): void
    {
        $deployJobs = $this->container->get(DeployJobRepository::class);
        assert($deployJobs instanceof DeployJobRepository);

        $now = TimestampImmutable::now();
        $deployJobs->create(new DeployJob(
            DeployJobIdentifier::create(),
            $this->project->id,
            $branch,
            'abcdef1234567890abcdef1234567890abcdef12',
            DeployJobStatus::Completed,
            '',
            $now,
            $now,
        ));
    }
}
