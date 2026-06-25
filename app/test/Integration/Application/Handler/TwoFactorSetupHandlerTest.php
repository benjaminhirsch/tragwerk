<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler;

use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Tragwerk\Application\Service\TwoFactor\TwoFactorService;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\RecoveryCodeRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\Repository\UserTwoFactorRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;
use function iterator_count;

final class TwoFactorSetupHandlerTest extends AppIntegrationTestCase
{
    private const string EMAIL    = '2fa-setup@example.com';
    private const string PASSWORD = 'correct-horse-battery-staple';

    private UserIdentifier $userId;

    /** The session id rotates on every request (autoRegenerate), so the cookie must be threaded. */
    private string $cookie = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedConfirmedUser();
    }

    #[Test]
    public function aUserCanEnrollEnableAndDisableTwoFactor(): void
    {
        $this->loginAndStoreCookie();

        // Starting setup creates an unconfirmed enrollment and renders the QR page.
        $setup = $this->authedDispatch('GET', '/account/2fa');
        self::assertSame(200, $setup->getStatusCode());

        $service = $this->container->get(TwoFactorService::class);
        assert($service instanceof TwoFactorService);
        $twoFactorRepository = $this->container->get(UserTwoFactorRepository::class);
        assert($twoFactorRepository instanceof UserTwoFactorRepository);

        $enrollment = $twoFactorRepository->getByUserId($this->userId);
        self::assertFalse($enrollment->isConfirmed());

        $secret = $service->decryptSecret($enrollment->secret);
        $enable = $this->authedDispatch('POST', '/account/2fa/enable', [
            'code' => TOTP::createFromSecret($secret)->now(),
        ]);
        self::assertSame(200, $enable->getStatusCode());

        $userRepository = $this->container->get(UserRepository::class);
        assert($userRepository instanceof UserRepository);
        self::assertTrue($userRepository->getById($this->userId)->hasTwoFactorEnabled());

        $recoveryRepository = $this->container->get(RecoveryCodeRepository::class);
        assert($recoveryRepository instanceof RecoveryCodeRepository);
        self::assertSame(10, iterator_count($recoveryRepository->getActiveByUserId($this->userId)));

        // Disabling tears everything down again.
        $disable = $this->authedDispatch('POST', '/account/2fa/disable', ['password' => self::PASSWORD]);
        self::assertSame(302, $disable->getStatusCode());
        self::assertSame($this->url('account'), $disable->getHeaderLine('Location'));

        self::assertFalse($userRepository->getById($this->userId)->hasTwoFactorEnabled());
        self::assertNull($twoFactorRepository->findByUserId($this->userId));
        self::assertSame(0, iterator_count($recoveryRepository->getActiveByUserId($this->userId)));
    }

    #[Test]
    public function enablingWithAWrongCodeReturnsToSetup(): void
    {
        $this->loginAndStoreCookie();
        $this->authedDispatch('GET', '/account/2fa');

        $enable = $this->authedDispatch('POST', '/account/2fa/enable', ['code' => '000000']);

        self::assertSame(302, $enable->getStatusCode());
        self::assertStringContainsString($this->url('2fa.setup'), $enable->getHeaderLine('Location'));

        $userRepository = $this->container->get(UserRepository::class);
        assert($userRepository instanceof UserRepository);
        self::assertFalse($userRepository->getById($this->userId)->hasTwoFactorEnabled());
    }

    private function loginAndStoreCookie(): void
    {
        $login = $this->dispatch('POST', '/login', [
            'email'    => self::EMAIL,
            'password' => self::PASSWORD,
        ]);
        self::assertSame('/', $login->getHeaderLine('Location'));

        $this->cookie = $this->getSessionCookie($login);
    }

    /** @param array<string, mixed> $body */
    private function authedDispatch(string $method, string $path, array $body = []): ResponseInterface
    {
        $response = $this->dispatch($method, $path, $body, $this->cookie);

        $fresh = $this->getSessionCookie($response);
        if ($fresh !== '') {
            $this->cookie = $fresh;
        }

        return $response;
    }

    private function seedConfirmedUser(): void
    {
        $this->userId = UserIdentifier::create();

        $now  = TimestampImmutable::now();
        $user = new User(
            $this->userId,
            self::EMAIL,
            'Setup',
            'User',
            PasswordHash::create(self::PASSWORD),
            $now,
            $now,
        );

        $userRepository = $this->container->get(UserRepository::class);
        assert($userRepository instanceof UserRepository);
        $userRepository->create($user);
        $userRepository->confirm($this->userId);
    }
}
