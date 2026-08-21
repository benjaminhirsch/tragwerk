<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Variables;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\EnvVar;
use Tragwerk\Domain\Repository\EnvVarRepository;
use Tragwerk\Domain\ValueObject\EnvVarIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Infrastructure\Git\BareRepository;
use TragwerkTest\Integration\Support\EnvironmentScopedTestCase;

use function assert;
use function iterator_to_array;

final class VariableHandlerTest extends EnvironmentScopedTestCase
{
    #[Test]
    public function indexRendersForActiveEnvironment(): void
    {
        $response = $this->dispatch('GET', $this->url('variable'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createGetRendersForm(): void
    {
        $response = $this->dispatch('GET', $this->url('variable.create'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithValidDataPersistsAndRedirects(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('variable.create'),
            ['key' => 'DATABASE_URL', 'value' => 'postgres://x'],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('variable'), $response->getHeaderLine('Location'));

        $repo = $this->container->get(EnvVarRepository::class);
        assert($repo instanceof EnvVarRepository);
        $vars = iterator_to_array($repo->findByBranch($this->project->id, $this->branch), false);
        self::assertCount(1, $vars);
        self::assertSame('DATABASE_URL', $vars[0]->key);
    }

    #[Test]
    public function indexRendersWithoutAnyEnvironment(): void
    {
        $this->dropOnlyBranch();

        $response = $this->dispatch('GET', $this->url('variable'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithProjectScopePersistsWithoutBranch(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('variable.create'),
            ['key' => 'SHARED_TOKEN', 'value' => 'abc', 'scope' => 'project'],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());

        $repo = $this->container->get(EnvVarRepository::class);
        assert($repo instanceof EnvVarRepository);

        $projectVars = iterator_to_array($repo->findProjectScoped($this->project->id), false);
        self::assertCount(1, $projectVars);
        self::assertSame('SHARED_TOKEN', $projectVars[0]->key);
        self::assertNull($projectVars[0]->branch);

        self::assertCount(0, iterator_to_array($repo->findByBranch($this->project->id, $this->branch), false));
    }

    #[Test]
    public function createPostWithProjectScopeWorksBeforeTheFirstPush(): void
    {
        $this->dropOnlyBranch();

        $response = $this->dispatch(
            'POST',
            $this->url('variable.create'),
            ['key' => 'EARLY_TOKEN', 'value' => 'abc', 'scope' => 'project'],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());

        $repo = $this->container->get(EnvVarRepository::class);
        assert($repo instanceof EnvVarRepository);
        self::assertCount(1, iterator_to_array($repo->findProjectScoped($this->project->id), false));
    }

    #[Test]
    public function createPostWithInvalidKeyReRendersForm(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('variable.create'),
            ['key' => 'not a key', 'value' => 'x'],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostUppercasesTheSubmittedKey(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('variable.create'),
            ['key' => 'database_url', 'value' => 'postgres://x'],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());

        $repo = $this->container->get(EnvVarRepository::class);
        assert($repo instanceof EnvVarRepository);
        $vars = iterator_to_array($repo->findByBranch($this->project->id, $this->branch), false);
        self::assertCount(1, $vars);
        self::assertSame('DATABASE_URL', $vars[0]->key);
    }

    #[Test]
    public function editAndDeleteOperateOnSeededVariable(): void
    {
        $var = $this->seedVar('API_TOKEN', 'old');

        $get = $this->dispatch(
            'GET',
            $this->url('variable.edit', ['id' => $var->id->toString()]),
            cookie: $this->sessionCookie,
        );
        self::assertSame(200, $get->getStatusCode());

        $delete = $this->dispatch(
            'POST',
            $this->url('variable.delete', ['id' => $var->id->toString()]),
            [],
            $this->sessionCookie,
        );
        self::assertSame(302, $delete->getStatusCode());

        $repo = $this->container->get(EnvVarRepository::class);
        assert($repo instanceof EnvVarRepository);
        self::assertCount(0, iterator_to_array($repo->findByBranch($this->project->id, $this->branch), false));
    }

    /** Leaves the project without any branch, i.e. in the state right after it was created. */
    private function dropOnlyBranch(): void
    {
        $bare = $this->container->get(BareRepository::class);
        assert($bare instanceof BareRepository);
        $bare->deleteBranch($this->project->id->toString(), $this->branch);
    }

    private function seedVar(string $key, string $value): EnvVar
    {
        $now = TimestampImmutable::now();
        $var = new EnvVar(
            EnvVarIdentifier::create(),
            $this->project->id,
            $this->branch,
            $key,
            $value,
            false,
            false,
            $now,
            $now,
        );

        $repo = $this->container->get(EnvVarRepository::class);
        assert($repo instanceof EnvVarRepository);
        $repo->create($var);

        return $var;
    }
}
