<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\User;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;

final class ChangePasswordHandlerTest extends AppIntegrationTestCase
{
    private const string EMAIL        = 'account-password@example.com';
    private const string PASSWORD     = 'old-password-123';
    private const string NEW_PASSWORD = 'brand-new-password-456';

    private string $cookie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedUser();
        $this->cookie = $this->loginAndGetCookie(self::PASSWORD);
    }

    #[Test]
    public function wrongCurrentPasswordDoesNotChangeIt(): void
    {
        $response = $this->dispatch('POST', $this->url('account.password'), [
            'currentPassword' => 'totally-wrong',
            'newPassword'     => self::NEW_PASSWORD,
            'confirmPassword' => self::NEW_PASSWORD,
        ], $this->cookie);

        self::assertSame(200, $response->getStatusCode());

        // Old password still works.
        self::assertSame('/', $this->login(self::PASSWORD)->getHeaderLine('Location'));
    }

    #[Test]
    public function correctCurrentPasswordChangesIt(): void
    {
        $response = $this->dispatch('POST', $this->url('account.password'), [
            'currentPassword' => self::PASSWORD,
            'newPassword'     => self::NEW_PASSWORD,
            'confirmPassword' => self::NEW_PASSWORD,
        ], $this->cookie);

        self::assertSame(302, $response->getStatusCode());

        // New password works, old one does not.
        self::assertSame('/', $this->login(self::NEW_PASSWORD)->getHeaderLine('Location'));
        self::assertSame(200, $this->login(self::PASSWORD)->getStatusCode());
    }

    private function seedUser(): void
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            self::EMAIL,
            'Account',
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

    private function login(string $password): ResponseInterface
    {
        return $this->dispatch('POST', $this->url('login'), [
            'email'    => self::EMAIL,
            'password' => $password,
        ]);
    }

    private function loginAndGetCookie(string $password): string
    {
        return $this->getSessionCookie($this->login($password));
    }
}
