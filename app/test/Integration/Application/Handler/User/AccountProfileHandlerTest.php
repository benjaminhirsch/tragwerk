<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\User;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;
use function is_string;

final class AccountProfileHandlerTest extends AppIntegrationTestCase
{
    private const string EMAIL    = 'account-profile@example.com';
    private const string PASSWORD = 'secure-password-123';

    private UserIdentifier $userId;
    private string $cookie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = $this->seedUser(self::EMAIL);
        $this->cookie = $this->loginAndGetCookie();
    }

    #[Test]
    public function updatingNameIsAppliedImmediately(): void
    {
        $response = $this->dispatch('POST', $this->url('account.profile'), [
            'firstname' => 'Renamed',
            'lastname'  => 'Person',
            'email'     => self::EMAIL,
        ], $this->cookie);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('Renamed', $this->reloadUser()->firstname);
        self::assertSame('Person', $this->reloadUser()->lastname);
    }

    #[Test]
    public function changingEmailIsDeferredUntilConfirmation(): void
    {
        $response = $this->dispatch('POST', $this->url('account.profile'), [
            'firstname' => 'Account',
            'lastname'  => 'Tester',
            'email'     => 'new-address@example.com',
        ], $this->cookie);

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('email-pending', $response->getHeaderLine('Location'));

        // The email is NOT changed yet — only a confirmation with the target was created.
        self::assertSame(self::EMAIL, $this->reloadUser()->email);

        $row = $this->connection->fetchAssociative(
            'SELECT token, new_email FROM email_confirmations WHERE user_id = ? AND new_email IS NOT NULL',
            [$this->userId->toString()],
        );
        assert($row !== false && is_string($row['token']));
        self::assertSame('new-address@example.com', $row['new_email']);

        // Following the confirmation link applies the change.
        $confirm = $this->dispatch('GET', '/confirm-email/' . $row['token']);
        self::assertSame(302, $confirm->getStatusCode());
        self::assertSame('new-address@example.com', $this->reloadUser()->email);
    }

    #[Test]
    public function changingToAnAlreadyUsedEmailIsRejected(): void
    {
        $this->seedUser('taken@example.com');

        $response = $this->dispatch('POST', $this->url('account.profile'), [
            'firstname' => 'Account',
            'lastname'  => 'Tester',
            'email'     => 'taken@example.com',
        ], $this->cookie);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(self::EMAIL, $this->reloadUser()->email);
    }

    private function reloadUser(): User
    {
        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);

        return $repository->getById($this->userId);
    }

    private function seedUser(string $email): UserIdentifier
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            $email,
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

        return $user->id;
    }

    private function loginAndGetCookie(): string
    {
        $response = $this->dispatch('POST', $this->url('login'), [
            'email'    => self::EMAIL,
            'password' => self::PASSWORD,
        ]);

        return $this->getSessionCookie($response);
    }
}
