<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler;

use League\Plates\Engine;
use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Application\Service\Registration\RegistrationSettings;
use Tragwerk\Application\Template\Extension\Registration;
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

    #[Test]
    public function getRedirectsToLoginWhenRegistrationIsDisabled(): void
    {
        $this->disableRegistration();

        $response = $this->dispatch('GET', '/register');

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('registration-disabled=1', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function postDoesNotCreateUserWhenRegistrationIsDisabled(): void
    {
        $this->disableRegistration();

        $response = $this->dispatch('POST', '/register', [
            'firstname' => 'Max',
            'lastname'  => 'Mustermann',
            'email'     => 'max@example.com',
            'password1' => 'secure-password-123',
            'password2' => 'secure-password-123',
        ]);

        self::assertSame(302, $response->getStatusCode());

        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);

        self::assertCount(0, [...$repository->getAll(emails: ['max@example.com'])]);
    }

    #[Test]
    public function loginPageLinksToRegistrationWhenEnabled(): void
    {
        $response = $this->dispatch('GET', '/login');

        self::assertStringContainsString('/register', (string) $response->getBody());
    }

    #[Test]
    public function loginPageHidesRegistrationLinkWhenDisabled(): void
    {
        $this->disableRegistration();

        $response = $this->dispatch('GET', '/login');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('/register', (string) $response->getBody());
    }

    private function disableRegistration(): void
    {
        $settings = new RegistrationSettings(false);

        $this->container->setAllowOverride(true);
        $this->container->setService(RegistrationSettings::class, $settings);
        $this->container->setAllowOverride(false);

        // Building Mezzio\Application already created the Plates engine, so the
        // extension it holds still carries the old settings. Swap its function.
        $engine = $this->container->get(Engine::class);
        assert($engine instanceof Engine);
        $engine->dropFunction('registrationEnabled');
        $engine->loadExtension(new Registration($settings));
    }
}
