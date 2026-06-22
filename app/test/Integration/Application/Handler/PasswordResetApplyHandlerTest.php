<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\PasswordReset;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\PasswordResetRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\PasswordResetIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;

final class PasswordResetApplyHandlerTest extends AppIntegrationTestCase
{
    #[Test]
    public function getWithUnknownTokenRendersInvalidPage(): void
    {
        $response = $this->dispatch('GET', '/password-reset/unknown-token');

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function postWithValidTokenUpdatesPasswordAndRedirectsToLogin(): void
    {
        $user = $this->seedUser('reset@example.com', 'old-password-123');
        $this->seedToken($user->id, 'valid-token', '+1 hour');

        $response = $this->dispatch('POST', '/password-reset/valid-token', [
            'password1' => 'brand-new-password',
            'password2' => 'brand-new-password',
        ]);

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/login', $response->getHeaderLine('Location'));

        $users = $this->container->get(UserRepository::class);
        assert($users instanceof UserRepository);
        self::assertNotNull($users->authenticate('reset@example.com', 'brand-new-password'));
        self::assertNull($users->authenticate('reset@example.com', 'old-password-123'));
    }

    #[Test]
    public function postWithExpiredTokenRendersInvalidPage(): void
    {
        $user = $this->seedUser('expired@example.com', 'old-password-123');
        $this->seedToken($user->id, 'expired-token', '-1 hour');

        $response = $this->dispatch('POST', '/password-reset/expired-token', [
            'password1' => 'brand-new-password',
            'password2' => 'brand-new-password',
        ]);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function postWithMismatchedPasswordsReRendersForm(): void
    {
        $user = $this->seedUser('mismatch@example.com', 'old-password-123');
        $this->seedToken($user->id, 'mismatch-token', '+1 hour');

        $response = $this->dispatch('POST', '/password-reset/mismatch-token', [
            'password1' => 'brand-new-password',
            'password2' => 'different-password',
        ]);

        self::assertSame(200, $response->getStatusCode());
    }

    private function seedUser(string $email, string $password): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            $email,
            'Reset',
            'Tester',
            PasswordHash::create($password),
            $now,
            $now,
        );

        $users = $this->container->get(UserRepository::class);
        assert($users instanceof UserRepository);
        $users->create($user);

        return $user;
    }

    private function seedToken(UserIdentifier $userId, string $token, string $expiry): void
    {
        $reset = new PasswordReset(
            PasswordResetIdentifier::create(),
            $userId,
            $token,
            TimestampImmutable::fromDateTime(new DateTimeImmutable($expiry)),
            TimestampImmutable::now(),
        );

        $repo = $this->container->get(PasswordResetRepository::class);
        assert($repo instanceof PasswordResetRepository);
        $repo->create($reset);
    }
}
