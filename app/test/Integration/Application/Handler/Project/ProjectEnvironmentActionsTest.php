<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Project;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Repository\DeployJobRepository;
use TragwerkTest\Integration\Support\EnvironmentScopedTestCase;

use function assert;

final class ProjectEnvironmentActionsTest extends EnvironmentScopedTestCase
{
    #[Test]
    public function redeployTriggersBuildAndRedirectsToEnvironmentShow(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('project.environment.redeploy', ['id' => $this->project->id->toString()]),
            ['branch' => $this->branch],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/environments/show?id=' . $this->branch, $response->getHeaderLine('Location'));
    }

    #[Test]
    public function syncDataCreatesDeployJobAndRedirects(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('project.environment.sync-data', ['id' => $this->project->id->toString()]),
            ['branch' => $this->branch],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());

        $repo = $this->container->get(DeployJobRepository::class);
        assert($repo instanceof DeployJobRepository);
        self::assertNotNull($repo->getLatestByProjectAndBranch($this->project->id, $this->branch));
    }
}
