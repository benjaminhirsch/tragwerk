<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Infrastructure\Repository;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\SshKey;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\SshKeyRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\SshKeyIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\IntegrationTestCase;

use function assert;

final class SshKeyRepositoryTest extends IntegrationTestCase
{
    private const string PUBLIC_KEY = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIM1N key@test';

    private SshKeyRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->container->get(SshKeyRepository::class);
        assert($repository instanceof SshKeyRepository);
        $this->repository = $repository;
    }

    #[Test]
    public function getByIdReturnsKey(): void
    {
        $user = $this->seedUser();
        $key  = new SshKey(
            SshKeyIdentifier::create(),
            $user->id,
            'laptop',
            self::PUBLIC_KEY,
            TimestampImmutable::now(),
        );
        $this->repository->create($key);

        $found = $this->repository->getById($key->id);

        self::assertInstanceOf(SshKey::class, $found);
        self::assertTrue($found->id->isEqualTo($key->id));
        self::assertTrue($found->userId->isEqualTo($user->id));
        self::assertSame(self::PUBLIC_KEY, $found->publicKey);
    }

    #[Test]
    public function getByIdReturnsNullWhenAbsent(): void
    {
        self::assertNull($this->repository->getById(SshKeyIdentifier::create()));
    }

    private function seedUser(): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            'sshkey-test@example.com',
            'Ssh',
            'Tester',
            PasswordHash::create('password123'),
            $now,
            $now,
        );

        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);
        $repository->create($user);

        return $user;
    }
}
