<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Event;

use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tragwerk\Domain\Entity\Domain;
use Tragwerk\Domain\Event\DomainAdded;
use Tragwerk\Domain\Repository\DomainRepository;
use Tragwerk\Domain\ValueObject\DomainIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use TragwerkTest\Integration\Support\EnvironmentScopedTestCase;

use function assert;

final class DomainAddedTest extends EnvironmentScopedTestCase
{
    #[Test]
    public function persistsDomainForEnvironment(): void
    {
        $domain = new Domain(
            id: DomainIdentifier::create(),
            projectId: $this->project->id,
            host: 'example.com',
            isPrimary: false,
            createdAt: TimestampImmutable::now(),
            branch: $this->branch,
        );

        $this->dispatcher()->dispatch(new DomainAdded($domain));

        $repository = $this->container->get(DomainRepository::class);
        assert($repository instanceof DomainRepository);
        $domains = $repository->findByEnvironment($this->project->id, $this->branch);

        self::assertCount(1, $domains);
        self::assertSame('example.com', $domains[0]->host);
    }

    private function dispatcher(): EventDispatcherInterface
    {
        $dispatcher = $this->container->get(EventDispatcherInterface::class);
        assert($dispatcher instanceof EventDispatcherInterface);

        return $dispatcher;
    }
}
