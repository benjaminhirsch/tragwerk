<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;

final class LoginHandlerTest extends AppIntegrationTestCase
{
    private const string EMAIL    = 'login-test@example.com';
    private const string PASSWORD = 'correct-horse-battery-staple';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedUser();
    }

    #[Test]
    public function getRendersLoginForm(): void
    {
        $response = $this->dispatch('GET', '/login');

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function postWithValidCredentialsRedirectsToHome(): void
    {
        $response = $this->dispatch('POST', '/login', [
            'email'    => self::EMAIL,
            'password' => self::PASSWORD,
        ]);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function postWithWrongPasswordReRendersLoginForm(): void
    {
        $response = $this->dispatch('POST', '/login', [
            'email'    => self::EMAIL,
            'password' => 'wrong-password',
        ]);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function postWithUnknownEmailReRendersLoginForm(): void
    {
        $response = $this->dispatch('POST', '/login', [
            'email'    => 'nobody@example.com',
            'password' => self::PASSWORD,
        ]);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function postWithUnconfirmedAccountReRendersLoginForm(): void
    {
        $this->seedUnconfirmedUser('unconfirmed@example.com', 'unconfirmed-pass-123');

        $response = $this->dispatch('POST', '/login', [
            'email'    => 'unconfirmed@example.com',
            'password' => 'unconfirmed-pass-123',
        ]);

        self::assertSame(200, $response->getStatusCode());
    }

    private function seedUser(): void
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            self::EMAIL,
            'Login',
            'Tester',
            PasswordHash::create(self::PASSWORD),
            $now,
            $now,
        );

        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);
        $repository->create($user);
        $repository->confirm($user->id);
    }

    private function seedUnconfirmedUser(string $email, string $password): void
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            $email,
            'Unconfirmed',
            'User',
            PasswordHash::create($password),
            $now,
            $now,
        );

        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);
        $repository->create($user);
    }
}
