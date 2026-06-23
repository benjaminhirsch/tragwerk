<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler;

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\EmailConfirmation;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\EmailConfirmationRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\EmailConfirmationIdentifier;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;

final class ConfirmEmailHandlerTest extends AppIntegrationTestCase
{
    #[Test]
    public function validTokenConfirmsUserAndRedirectsToLogin(): void
    {
        $user = $this->seedUnconfirmedUser('confirm-me@example.com');
        $this->seedConfirmation($user->id, 'valid-token', TimestampImmutable::fromDateTime(
            (new DateTimeImmutable())->add(new DateInterval('PT1H')),
        ));

        $response = $this->dispatch('GET', '/confirm-email/valid-token');

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/login', $response->getHeaderLine('Location'));
        self::assertStringContainsString('confirmed=1', $response->getHeaderLine('Location'));

        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);
        $confirmed = $repository->getById($user->id);
        assert($confirmed instanceof User);
        self::assertNotNull($confirmed->confirmedAt);
    }

    #[Test]
    public function unknownTokenRendersErrorPage(): void
    {
        $response = $this->dispatch('GET', '/confirm-email/does-not-exist');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('Location', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function expiredTokenRendersErrorPageAndDoesNotConfirmUser(): void
    {
        $user = $this->seedUnconfirmedUser('expired@example.com');
        $this->seedConfirmation($user->id, 'expired-token', TimestampImmutable::fromDateTime(
            (new DateTimeImmutable())->sub(new DateInterval('PT1H')),
        ));

        $response = $this->dispatch('GET', '/confirm-email/expired-token');

        self::assertSame(200, $response->getStatusCode());

        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);
        $stillUnconfirmed = $repository->getById($user->id);
        assert($stillUnconfirmed instanceof User);
        self::assertNull($stillUnconfirmed->confirmedAt);
    }

    private function seedUnconfirmedUser(string $email): User
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

        return $user;
    }

    private function seedConfirmation(UserIdentifier $userId, string $token, TimestampImmutable $expiresAt): void
    {
        $confirmation = new EmailConfirmation(
            EmailConfirmationIdentifier::create(),
            $userId,
            $token,
            $expiresAt,
            TimestampImmutable::now(),
        );

        $repository = $this->container->get(EmailConfirmationRepository::class);
        assert($repository instanceof EmailConfirmationRepository);
        $repository->create($confirmation);
    }
}
