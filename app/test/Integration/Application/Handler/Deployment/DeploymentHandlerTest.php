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

    #[Test]
    public function indexPreselectsRequestedDeployJob(): void
    {
        // Older job is the one we explicitly request; the newer job would be
        // selected by default, so finding the older output proves the override.
        $older = $this->seedDeployJob('older deploy output', '2026-06-20 10:00:00.111111');
        $this->seedDeployJob('newer deploy output', '2026-06-21 10:00:00.222222');

        $response = $this->dispatch(
            'GET',
            $this->url('deployment') . '?selected=' . $older->id->toString(),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringContainsString('older deploy output', $body);
        self::assertStringNotContainsString('newer deploy output', $body);
    }

    #[Test]
    public function indexMarksRequestedDeployJobActiveInList(): void
    {
        $older = $this->seedDeployJob('older deploy output', '2026-06-20 10:00:00.111111');
        $newer = $this->seedDeployJob('newer deploy output', '2026-06-21 10:00:00.222222');

        $response = $this->dispatch(
            'GET',
            $this->url('deployment') . '?selected=' . $older->id->toString(),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();

        // The requested (older) entry carries the active class; the newer one does not.
        self::assertMatchesRegularExpression(
            '/class="log-item active"[^>]*hx-get="[^"]*' . $older->id->toString() . '/',
            $body,
        );
        self::assertDoesNotMatchRegularExpression(
            '/class="log-item active"[^>]*hx-get="[^"]*' . $newer->id->toString() . '/',
            $body,
        );
    }

    #[Test]
    public function indexIgnoresUnknownSelectedAndFallsBackToNewest(): void
    {
        $this->seedDeployJob('newest deploy output', '2026-06-21 10:00:00.222222');

        $response = $this->dispatch(
            'GET',
            $this->url('deployment') . '?selected=' . DeployJobIdentifier::create()->toString(),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('newest deploy output', (string) $response->getBody());
    }

    #[Test]
    public function indexIgnoresMalformedSelectedParam(): void
    {
        $this->seedDeployJob('seeded deploy output', '2026-06-21 10:00:00.222222');

        $response = $this->dispatch(
            'GET',
            $this->url('deployment') . '?selected=not-a-uuid',
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('seeded deploy output', (string) $response->getBody());
    }

    private function seedDeployJob(string $output = 'deploy output', string|null $createdAt = null): DeployJob
    {
        $ts  = $createdAt !== null ? TimestampImmutable::fromString($createdAt) : TimestampImmutable::now();
        $job = new DeployJob(
            DeployJobIdentifier::create(),
            $this->project->id,
            $this->branch,
            'abcdef1234567890abcdef1234567890abcdef12',
            DeployJobStatus::Completed,
            $output,
            $ts,
            $ts,
        );

        $repo = $this->container->get(DeployJobRepository::class);
        assert($repo instanceof DeployJobRepository);
        $repo->create($job);

        return $job;
    }
}
