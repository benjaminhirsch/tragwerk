<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\UserRepository;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;

final class UserRegisterHandlerTest extends AppIntegrationTestCase
{
    #[Test]
    public function getRendersRegistrationForm(): void
    {
        $response = $this->dispatch('GET', '/register');

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function postWithValidDataRedirectsToLogin(): void
    {
        $response = $this->dispatch('POST', '/register', [
            'firstname' => 'Max',
            'lastname'  => 'Mustermann',
            'email'     => 'max@example.com',
            'password1' => 'secure-password-123',
            'password2' => 'secure-password-123',
        ]);

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/login', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function postWithValidDataPersistsUserInDatabase(): void
    {
        $this->dispatch('POST', '/register', [
            'firstname' => 'Max',
            'lastname'  => 'Mustermann',
            'email'     => 'max@example.com',
            'password1' => 'secure-password-123',
            'password2' => 'secure-password-123',
        ]);

        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);
        $user = $repository->getAll(emails: ['max@example.com'])->current();

        self::assertInstanceOf(User::class, $user);
        self::assertSame('Max', $user->firstname);
        self::assertSame('Mustermann', $user->lastname);
    }

    #[Test]
    public function postWithMismatchedPasswordsReRendersForm(): void
    {
        $response = $this->dispatch('POST', '/register', [
            'firstname' => 'Max',
            'lastname'  => 'Mustermann',
            'email'     => 'max@example.com',
            'password1' => 'password-one',
            'password2' => 'password-two',
        ]);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function postWithTooShortPasswordReRendersForm(): void
    {
        $response = $this->dispatch('POST', '/register', [
            'firstname' => 'Max',
            'lastname'  => 'Mustermann',
            'email'     => 'max@example.com',
            'password1' => 'short',
            'password2' => 'short',
        ]);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function postWithAlreadyRegisteredEmailReRendersForm(): void
    {
        $payload = [
            'firstname' => 'Max',
            'lastname'  => 'Mustermann',
            'email'     => 'max@example.com',
            'password1' => 'secure-password-123',
            'password2' => 'secure-password-123',
        ];

        $this->dispatch('POST', '/register', $payload);

        $response = $this->dispatch('POST', '/register', $payload);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function postWithAlreadyRegisteredEmailDoesNotCreateDuplicateUser(): void
    {
        $payload = [
            'firstname' => 'Max',
            'lastname'  => 'Mustermann',
            'email'     => 'max@example.com',
            'password1' => 'secure-password-123',
            'password2' => 'secure-password-123',
        ];

        $this->dispatch('POST', '/register', $payload);
        $this->dispatch('POST', '/register', $payload);

        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);
        $users = [...$repository->getAll(emails: ['max@example.com'])];

        self::assertCount(1, $users);
    }
}
