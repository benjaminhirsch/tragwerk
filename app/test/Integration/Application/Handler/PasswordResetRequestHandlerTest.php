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
use function is_numeric;

final class PasswordResetRequestHandlerTest extends AppIntegrationTestCase
{
    #[Test]
    public function getRendersRequestForm(): void
    {
        $response = $this->dispatch('GET', '/password-reset');

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function postWithKnownEmailRedirectsToLogin(): void
    {
        $this->seedUser('known@example.com');

        $response = $this->dispatch('POST', '/password-reset', ['email' => 'known@example.com']);

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/login', $response->getHeaderLine('Location'));
        self::assertStringContainsString('reset-requested=1', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function postWithKnownEmailPersistsPasswordReset(): void
    {
        $this->seedUser('known@example.com');

        $this->dispatch('POST', '/password-reset', ['email' => 'known@example.com']);

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM password_resets');
        assert(is_numeric($count));
        self::assertSame(1, (int) $count);
    }

    #[Test]
    public function postWithUnknownEmailStillRedirectsToLogin(): void
    {
        $response = $this->dispatch('POST', '/password-reset', ['email' => 'nobody@example.com']);

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('reset-requested=1', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function postWithUnknownEmailDoesNotPersistPasswordReset(): void
    {
        $this->dispatch('POST', '/password-reset', ['email' => 'nobody@example.com']);

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM password_resets');
        assert(is_numeric($count));
        self::assertSame(0, (int) $count);
    }

    #[Test]
    public function postWithMissingEmailReRendersForm(): void
    {
        $response = $this->dispatch('POST', '/password-reset', ['unexpected' => 'value']);

        self::assertSame(200, $response->getStatusCode());
    }

    private function seedUser(string $email): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            $email,
            'Test',
            'User',
            PasswordHash::create('secure-password-123'),
            $now,
            $now,
        );

        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);
        $repository->create($user);
        $repository->confirm($user->id);

        return $user;
    }
}
