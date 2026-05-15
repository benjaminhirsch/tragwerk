<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Domain\Repository;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\IntegrationTestCase;

use function assert;
use function iterator_to_array;

final class UserRepositoryTest extends IntegrationTestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);
        $this->repository = $repository;
    }

    #[Test]
    public function createPersistsUserToDatabase(): void
    {
        $user = $this->makeUser();

        $this->repository->create($user);

        $found = $this->repository->getAll(emails: [$user->email])->current();
        self::assertInstanceOf(User::class, $found);
        self::assertTrue($user->id->isEqualTo($found->id));
        self::assertSame($user->email, $found->email);
        self::assertSame($user->firstname, $found->firstname);
        self::assertSame($user->lastname, $found->lastname);
    }

    #[Test]
    public function getByIdReturnsPersistedUser(): void
    {
        $user = $this->makeUser();
        $this->repository->create($user);

        $found = $this->repository->getById($user->id);

        self::assertInstanceOf(User::class, $found);
        self::assertTrue($user->id->isEqualTo($found->id));
    }

    #[Test]
    public function getByIdThrowsForUnknownId(): void
    {
        $this->expectException(EntityNotFound::class);

        $this->repository->getById(UserIdentifier::create());
    }

    #[Test]
    public function authenticateReturnsUserInterfaceForValidCredentials(): void
    {
        $rawPassword = 'correct-horse-battery-staple';
        $user        = $this->makeUser(password: $rawPassword);
        $this->repository->create($user);

        $result = $this->repository->authenticate($user->email, $rawPassword);

        self::assertNotNull($result);
        // Identity is the UUID, not the email — see UserRepository::authenticate()
        self::assertSame($user->id->toString(), $result->getIdentity());
    }

    #[Test]
    public function authenticateReturnsNullForWrongPassword(): void
    {
        $user = $this->makeUser(password: 'correct-password');
        $this->repository->create($user);

        $result = $this->repository->authenticate($user->email, 'wrong-password');

        self::assertNull($result);
    }

    #[Test]
    public function authenticateReturnsNullForUnknownEmail(): void
    {
        $result = $this->repository->authenticate('nobody@example.com', 'any-password');

        self::assertNull($result);
    }

    #[Test]
    public function getAllWithoutFilterReturnsAllUsers(): void
    {
        $this->repository->create($this->makeUser(email: 'a@example.com'));
        $this->repository->create($this->makeUser(email: 'b@example.com'));

        $all = iterator_to_array($this->repository->getAll());

        self::assertCount(2, $all);
    }

    #[Test]
    public function getAllFiltersByEmail(): void
    {
        $this->repository->create($this->makeUser(email: 'a@example.com'));
        $this->repository->create($this->makeUser(email: 'b@example.com'));

        // Note: getAll(emails:) uses implode(',', $emails) as a single parameter,
        // which only works correctly for exactly one email. Filtering by multiple
        // emails is broken in the current UserRepository implementation.
        $results = iterator_to_array($this->repository->getAll(emails: ['a@example.com']));

        self::assertCount(1, $results);
        self::assertInstanceOf(User::class, $results[0]);
        self::assertSame('a@example.com', $results[0]->email);
    }

    private function makeUser(
        string $email = 'test@example.com',
        string $password = 'test-password-123',
    ): User {
        $now = TimestampImmutable::now();

        return new User(
            UserIdentifier::create(),
            $email,
            'Max',
            'Mustermann',
            null,
            PasswordHash::create($password),
            $now,
            $now,
        );
    }
}
