<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler;

use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Tragwerk\Application\Service\TwoFactor\TwoFactorService;
use Tragwerk\Domain\Entity\RecoveryCode;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Entity\UserTwoFactor;
use Tragwerk\Domain\Repository\RecoveryCodeRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\Repository\UserTwoFactorRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\RecoveryCodeIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use Tragwerk\Domain\ValueObject\UserTwoFactorIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;
use function preg_match;

final class TwoFactorChallengeHandlerTest extends AppIntegrationTestCase
{
    private const string EMAIL    = '2fa-test@example.com';
    private const string PASSWORD = 'correct-horse-battery-staple';
    private const string RECOVERY = 'ABCDE-FGHJK';

    private UserIdentifier $userId;
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedUserWithTwoFactor();
    }

    #[Test]
    public function loginWithTwoFactorRedirectsToChallengeAndBlocksProtectedRoutes(): void
    {
        $login = $this->login();

        self::assertSame(302, $login->getStatusCode());
        self::assertSame($this->url('2fa.challenge'), $login->getHeaderLine('Location'));

        $cookie = $this->getSessionCookie($login);

        // A protected route must divert to the challenge, NOT to /login.
        $blocked = $this->dispatch('GET', '/account', cookie: $cookie);
        self::assertSame(302, $blocked->getStatusCode());
        self::assertSame($this->url('2fa.challenge'), $blocked->getHeaderLine('Location'));
    }

    #[Test]
    public function validTotpCodeCompletesLogin(): void
    {
        $cookie = $this->getSessionCookie($this->login());

        $verify = $this->dispatch('POST', '/login/2fa', ['code' => $this->currentCode()], $cookie);
        self::assertSame(302, $verify->getStatusCode());
        self::assertSame('/', $verify->getHeaderLine('Location'));

        // The now-full session may reach protected routes.
        $account = $this->dispatch('GET', '/account', cookie: $this->getSessionCookie($verify));
        self::assertSame(200, $account->getStatusCode());
    }

    #[Test]
    public function invalidCodeStaysOnTheChallenge(): void
    {
        $cookie = $this->getSessionCookie($this->login());

        $verify = $this->dispatch('POST', '/login/2fa', ['code' => '000000'], $cookie);
        self::assertSame(200, $verify->getStatusCode());
    }

    #[Test]
    public function recoveryCodeCompletesLoginOnceThenIsConsumed(): void
    {
        $first = $this->dispatch(
            'POST',
            '/login/2fa',
            ['recovery_code' => self::RECOVERY],
            $this->getSessionCookie($this->login()),
        );
        self::assertSame(302, $first->getStatusCode());
        self::assertSame('/', $first->getHeaderLine('Location'));

        // Re-using the same recovery code must fail.
        $second = $this->dispatch(
            'POST',
            '/login/2fa',
            ['recovery_code' => self::RECOVERY],
            $this->getSessionCookie($this->login()),
        );
        self::assertSame(200, $second->getStatusCode());
    }

    #[Test]
    public function trustingTheDeviceSkipsTheChallengeOnTheNextLogin(): void
    {
        $cookie = $this->getSessionCookie($this->login());

        $verify = $this->dispatch(
            'POST',
            '/login/2fa',
            ['code' => $this->currentCode(), 'trust_device' => '1'],
            $cookie,
        );
        self::assertSame(302, $verify->getStatusCode());

        $trustCookie = $this->extractCookie($verify, 'tragwerk-2fa-trust');
        self::assertNotSame('', $trustCookie);

        // A fresh login carrying the trust cookie goes straight home.
        $relogin = $this->dispatch('POST', '/login', [
            'email'    => self::EMAIL,
            'password' => self::PASSWORD,
        ], $trustCookie);

        self::assertSame(302, $relogin->getStatusCode());
        self::assertSame('/', $relogin->getHeaderLine('Location'));
    }

    private function login(): ResponseInterface
    {
        return $this->dispatch('POST', '/login', [
            'email'    => self::EMAIL,
            'password' => self::PASSWORD,
        ]);
    }

    private function currentCode(): string
    {
        assert($this->secret !== '');

        return TOTP::createFromSecret($this->secret)->now();
    }

    private function extractCookie(ResponseInterface $response, string $name): string
    {
        foreach ($response->getHeader('Set-Cookie') as $header) {
            if (preg_match('/' . $name . '=[^;]+/', $header, $m) === 1) {
                return $m[0];
            }
        }

        return '';
    }

    private function seedUserWithTwoFactor(): void
    {
        $service = $this->container->get(TwoFactorService::class);
        assert($service instanceof TwoFactorService);

        $this->userId = UserIdentifier::create();
        $this->secret = $service->generateSecret();

        $now  = TimestampImmutable::now();
        $user = new User(
            $this->userId,
            self::EMAIL,
            'Two',
            'Factor',
            PasswordHash::create(self::PASSWORD),
            $now,
            $now,
        );

        $userRepository = $this->container->get(UserRepository::class);
        assert($userRepository instanceof UserRepository);
        $userRepository->create($user);
        $userRepository->confirm($this->userId);

        $twoFactorRepository = $this->container->get(UserTwoFactorRepository::class);
        assert($twoFactorRepository instanceof UserTwoFactorRepository);
        $twoFactorRepository->create(new UserTwoFactor(
            id: UserTwoFactorIdentifier::create(),
            userId: $this->userId,
            secret: $service->encryptSecret($this->secret),
            createdAt: $now,
            updatedAt: $now,
        ));
        $twoFactorRepository->confirm($this->userId);

        $recoveryRepository = $this->container->get(RecoveryCodeRepository::class);
        assert($recoveryRepository instanceof RecoveryCodeRepository);
        $recoveryRepository->create(new RecoveryCode(
            id: RecoveryCodeIdentifier::create(),
            userId: $this->userId,
            codeHash: $service->hashRecoveryCode(self::RECOVERY),
            createdAt: $now,
        ));
    }
}
