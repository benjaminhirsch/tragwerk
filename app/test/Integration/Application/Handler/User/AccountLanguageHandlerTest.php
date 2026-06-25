<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\User;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Enum\Locale;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;
use function str_contains;

final class AccountLanguageHandlerTest extends AppIntegrationTestCase
{
    private const string EMAIL    = 'account-language@example.com';
    private const string PASSWORD = 'secure-password-123';

    private UserIdentifier $userId;
    private string $cookie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = $this->seedUser();
        $this->cookie = $this->loginAndGetCookie();
    }

    #[Test]
    public function selectingEnglishIsPersisted(): void
    {
        $response = $this->dispatch('POST', $this->url('account.language'), ['locale' => 'en_US'], $this->cookie);

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('language-saved=1', $response->getHeaderLine('Location'));
        self::assertSame(Locale::EN_US, $this->reloadUser()->locale);
    }

    #[Test]
    public function selectingGermanIsPersisted(): void
    {
        $response = $this->dispatch('POST', $this->url('account.language'), ['locale' => 'de_DE'], $this->cookie);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(Locale::DE_DE, $this->reloadUser()->locale);
    }

    #[Test]
    public function automaticSelectionClearsThePreference(): void
    {
        // First set a concrete language, then reset to automatic. The session id
        // rotates per request, so the cookie must be threaded between requests.
        $first = $this->dispatch('POST', $this->url('account.language'), ['locale' => 'de_DE'], $this->cookie);
        self::assertSame(Locale::DE_DE, $this->reloadUser()->locale);
        $cookie = $this->getSessionCookie($first) ?: $this->cookie;

        $response = $this->dispatch('POST', $this->url('account.language'), ['locale' => ''], $cookie);

        self::assertSame(302, $response->getStatusCode());
        self::assertNull($this->reloadUser()->locale);
    }

    #[Test]
    public function invalidLocaleIsRejectedAndNotPersisted(): void
    {
        $response = $this->dispatch('POST', $this->url('account.language'), ['locale' => 'xx_XX'], $this->cookie);

        self::assertSame(200, $response->getStatusCode());
        self::assertNull($this->reloadUser()->locale);
    }

    #[Test]
    public function savedLanguageAppliesImmediatelyInTheSameSession(): void
    {
        // Default without preference is English; after saving German the very next
        // request must already render German (session override).
        $save   = $this->dispatch('POST', $this->url('account.language'), ['locale' => 'de_DE'], $this->cookie);
        $cookie = $this->getSessionCookie($save) ?: $this->cookie;

        $account = $this->dispatch('GET', $this->url('account'), [], $cookie);

        self::assertSame(200, $account->getStatusCode());
        self::assertTrue(str_contains((string) $account->getBody(), 'lang="de"'));
    }

    private function reloadUser(): User
    {
        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);

        return $repository->getById($this->userId);
    }

    private function seedUser(): UserIdentifier
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
