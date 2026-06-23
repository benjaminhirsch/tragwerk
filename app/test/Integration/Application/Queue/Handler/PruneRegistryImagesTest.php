<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Queue\Handler;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Application\Queue\Handler\PruneRegistryImages;
use Tragwerk\Application\Queue\Message;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\Service\RegistryPruner;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\IntegrationTestCase;
use TragwerkTest\Integration\Support\RecordingLogger;

use function assert;

final class PruneRegistryImagesTest extends IntegrationTestCase
{
    #[Test]
    public function unknownRegistryIdIsLoggedAndSkipped(): void
    {
        $logger  = new RecordingLogger();
        $handler = new PruneRegistryImages($this->registryRepository(), new RegistryPruner(), $logger);

        $handler->handle(new Message\PruneRegistryImages(
            RegistryIdentifier::create()->toString(),
            'app',
            'main',
        ));

        self::assertContains('Registry not found for pruning', $logger->messages);
    }

    #[Test]
    public function disabledPruningIsANoOp(): void
    {
        $registry = $this->seedRegistry(pruningEnabled: false);

        $logger  = new RecordingLogger();
        $handler = new PruneRegistryImages($this->registryRepository(), new RegistryPruner(), $logger);

        $handler->handle(new Message\PruneRegistryImages($registry->id->toString(), 'app', 'main'));

        // Pruning is disabled, so no network call happens and nothing is logged.
        self::assertSame([], $logger->messages);
    }

    private function registryRepository(): RegistryRepository
    {
        $repo = $this->container->get(RegistryRepository::class);
        assert($repo instanceof RegistryRepository);

        return $repo;
    }

    private function seedRegistry(bool $pruningEnabled): Registry
    {
        $now    = TimestampImmutable::now();
        $userId = UserIdentifier::create();

        $users = $this->container->get(UserRepository::class);
        assert($users instanceof UserRepository);
        $users->create(new User(
            $userId,
            'prune-test@example.com',
            'Prune',
            'Tester',
            PasswordHash::create('password'),
            $now,
            $now,
        ));

        $teamId = TeamIdentifier::create();
        $teams  = $this->container->get(TeamRepository::class);
        assert($teams instanceof TeamRepository);
        $teams->create(new Team($teamId, 'Test Team', $userId, $now, $userId, $now, $userId));

        $registry = new Registry(
            RegistryIdentifier::create(),
            'Reg',
            'registry.example.com',
            'repo',
            'user',
            'pass',
            $pruningEnabled,
            10,
            $teamId,
            $now,
            $userId,
            $now,
            $userId,
        );

        $this->registryRepository()->create($registry);

        return $registry;
    }
}
