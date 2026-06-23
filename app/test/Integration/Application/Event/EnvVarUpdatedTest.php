<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Event;

use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tragwerk\Domain\Entity\EnvVar;
use Tragwerk\Domain\Event\EnvVarUpdated;
use Tragwerk\Domain\Repository\EnvVarRepository;
use Tragwerk\Domain\ValueObject\EnvVarIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use TragwerkTest\Integration\Support\EnvironmentScopedTestCase;

use function assert;

final class EnvVarUpdatedTest extends EnvironmentScopedTestCase
{
    #[Test]
    public function updatesPersistedValue(): void
    {
        $repository = $this->container->get(EnvVarRepository::class);
        assert($repository instanceof EnvVarRepository);

        $id  = EnvVarIdentifier::create();
        $now = TimestampImmutable::now();
        $repository->create(new EnvVar(
            id: $id,
            projectId: $this->project->id,
            branch: $this->branch,
            key: 'DATABASE_URL',
            value: 'postgres://old',
            isSecret: false,
            isInherited: false,
            createdAt: $now,
            updatedAt: $now,
        ));

        $this->dispatcher()->dispatch(new EnvVarUpdated(new EnvVar(
            id: $id,
            projectId: $this->project->id,
            branch: $this->branch,
            key: 'DATABASE_URL',
            value: 'postgres://new',
            isSecret: false,
            isInherited: false,
            createdAt: $now,
            updatedAt: TimestampImmutable::now(),
        )));

        self::assertSame('postgres://new', $repository->getById($id)->value);
    }

    private function dispatcher(): EventDispatcherInterface
    {
        $dispatcher = $this->container->get(EventDispatcherInterface::class);
        assert($dispatcher instanceof EventDispatcherInterface);

        return $dispatcher;
    }
}
