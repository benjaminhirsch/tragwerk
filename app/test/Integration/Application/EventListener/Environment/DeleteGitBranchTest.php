<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\EventListener\Environment;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Application\EventListener\Environment\DeleteGitBranch;
use Tragwerk\Domain\Event\EnvironmentDeleted;
use Tragwerk\Infrastructure\Git\BareRepository;
use TragwerkTest\Integration\Support\EnvironmentScopedTestCase;

use function assert;
use function escapeshellarg;
use function exec;

final class DeleteGitBranchTest extends EnvironmentScopedTestCase
{
    #[Test]
    public function deletesOnlyTheGivenBranch(): void
    {
        $bare = $this->container->get(BareRepository::class);
        assert($bare instanceof BareRepository);

        $projectId = $this->project->id->toString();
        $repoPath  = $bare->getPath($projectId);
        exec('git -C ' . escapeshellarg($repoPath) . ' branch feature main 2>/dev/null');

        self::assertContains('feature', $bare->getBranches($projectId));

        $listener = new DeleteGitBranch($bare);
        $listener(new EnvironmentDeleted($this->project->id, 'feature'));

        $branches = $bare->getBranches($projectId);
        self::assertNotContains('feature', $branches);
        self::assertContains('main', $branches);
    }

    #[Test]
    public function missingBranchIsNoOp(): void
    {
        $bare = $this->container->get(BareRepository::class);
        assert($bare instanceof BareRepository);

        $listener = new DeleteGitBranch($bare);
        $listener(new EnvironmentDeleted($this->project->id, 'never-existed'));

        self::assertContains('main', $bare->getBranches($this->project->id->toString()));
    }
}
