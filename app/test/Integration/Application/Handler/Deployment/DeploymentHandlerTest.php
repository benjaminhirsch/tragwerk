<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Deployment;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\DeployJob;
use Tragwerk\Domain\Enum\DeployJobStatus;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\ValueObject\DeployJobIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use TragwerkTest\Integration\Support\EnvironmentScopedTestCase;

use function assert;

final class DeploymentHandlerTest extends EnvironmentScopedTestCase
{
    #[Test]
    public function indexRendersForActiveProject(): void
    {
        $response = $this->dispatch('GET', $this->url('deployment'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function indexAcceptsFilterAndFragmentParams(): void
    {
        foreach (['?type=build', '?type=deploy', '?fragment=1', '?panel=1'] as $query) {
            $response = $this->dispatch('GET', $this->url('deployment') . $query, cookie: $this->sessionCookie);

            self::assertSame(200, $response->getStatusCode(), 'query ' . $query);
        }
    }

    #[Test]
    public function terminalRendersForSeededDeployJob(): void
    {
        $job = $this->seedDeployJob();

        $response = $this->dispatch(
            'GET',
            $this->url('deployment.terminal') . '?kind=deploy&id=' . $job->id->toString(),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    private function seedDeployJob(): DeployJob
    {
        $now = TimestampImmutable::now();
        $job = new DeployJob(
            DeployJobIdentifier::create(),
            $this->project->id,
            $this->branch,
            'abcdef1234567890abcdef1234567890abcdef12',
            DeployJobStatus::Completed,
            'deploy output',
            $now,
            $now,
        );

        $repo = $this->container->get(DeployJobRepository::class);
        assert($repo instanceof DeployJobRepository);
        $repo->create($job);

        return $job;
    }
}
