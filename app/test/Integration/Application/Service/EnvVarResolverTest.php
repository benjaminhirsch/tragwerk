<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Service;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Application\Service\EnvVarResolver;
use Tragwerk\Domain\Entity\EnvVar;
use Tragwerk\Domain\Repository\EnvVarRepository;
use Tragwerk\Domain\ValueObject\EnvVarIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use TragwerkTest\Integration\Support\EnvironmentScopedTestCase;

use function assert;

final class EnvVarResolverTest extends EnvironmentScopedTestCase
{
    #[Test]
    public function projectScopedVarsReachAnEnvironment(): void
    {
        $this->seedVar(null, 'SHARED', 'from-project');

        self::assertSame(['SHARED' => 'from-project'], $this->resolve());
    }

    #[Test]
    public function branchLocalVarOverridesProjectScope(): void
    {
        $this->seedVar(null, 'SHARED', 'from-project');
        $this->seedVar($this->branch, 'SHARED', 'from-branch');

        self::assertSame(['SHARED' => 'from-branch'], $this->resolve());
    }

    #[Test]
    public function projectScopeIsTheBaseLayerForUnrelatedKeys(): void
    {
        $this->seedVar(null, 'SHARED', 'from-project');
        $this->seedVar($this->branch, 'LOCAL', 'from-branch');

        self::assertSame(
            ['SHARED' => 'from-project', 'LOCAL' => 'from-branch'],
            $this->resolve(),
        );
    }

    /** @return array<string, string> */
    private function resolve(): array
    {
        $resolver = $this->container->get(EnvVarResolver::class);
        assert($resolver instanceof EnvVarResolver);

        return $resolver->resolve($this->project->id, $this->branch);
    }

    private function seedVar(string|null $branch, string $key, string $value): void
    {
        $now = TimestampImmutable::now();

        $repo = $this->container->get(EnvVarRepository::class);
        assert($repo instanceof EnvVarRepository);
        $repo->create(new EnvVar(
            EnvVarIdentifier::create(),
            $this->project->id,
            $branch,
            $key,
            $value,
            false,
            false,
            $now,
            $now,
        ));
    }
}
