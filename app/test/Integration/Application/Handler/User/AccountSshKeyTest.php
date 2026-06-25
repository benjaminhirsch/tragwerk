<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\User;

use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\EC\PublicKey as ECPublicKey;
use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\SshKey;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\SshKeyRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\SshKeyIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;
use function iterator_to_array;

final class AccountSshKeyTest extends AppIntegrationTestCase
{
    private const string EMAIL    = 'account-ssh@example.com';
    private const string PASSWORD = 'secure-password-123';

    private User $user;
    private string $sessionCookie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user          = $this->seedUser();
        $this->sessionCookie = $this->loginAndGetCookie();
    }

    #[Test]
    public function getRendersAccountPage(): void
    {
        $response = $this->dispatch('GET', $this->url('account'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function getRedirectsUnauthenticatedUserToLogin(): void
    {
        $response = $this->dispatch('GET', $this->url('account'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('login'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function addingAValidSshKeyPersistsItAndRedirects(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('account.ssh-keys.add'),
            ['name' => 'Deploy Key', 'publicKey' => self::makePublicKey()],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/account', $response->getHeaderLine('Location'));

        $repository = $this->container->get(SshKeyRepository::class);
        assert($repository instanceof SshKeyRepository);
        $keys = iterator_to_array($repository->getByUserId($this->user->id), false);
        self::assertCount(1, $keys);
        self::assertSame('Deploy Key', $keys[0]->name);
    }

    #[Test]
    public function addingAnInvalidSshKeyReRendersTheForm(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('account.ssh-keys.add'),
            ['name' => 'My Key', 'publicKey' => 'not-a-valid-key'],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function deletingAnSshKeyRemovesItFromTheDatabase(): void
    {
        $key = $this->seedSshKey();

        $response = $this->dispatch(
            'POST',
            $this->url('account.ssh-keys.delete'),
            ['keyId' => $key->id->toString()],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());

        $repository = $this->container->get(SshKeyRepository::class);
        assert($repository instanceof SshKeyRepository);
        self::assertCount(0, iterator_to_array($repository->getByUserId($this->user->id), false));
    }

    #[Test]
    public function deletingAnUnknownKeyIdStillRedirects(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('account.ssh-keys.delete'),
            ['keyId' => SshKeyIdentifier::create()->toString()],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
    }

    private function seedUser(): User
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

        return $user;
    }

    private function seedSshKey(string $name = 'Test Key'): SshKey
    {
        $key = new SshKey(
            SshKeyIdentifier::create(),
            $this->user->id,
            $name,
            self::makePublicKey(),
            TimestampImmutable::now(),
        );

        $repository = $this->container->get(SshKeyRepository::class);
        assert($repository instanceof SshKeyRepository);
        $repository->create($key);

        return $key;
    }

    private static function makePublicKey(): string
    {
        $pub = EC::createKey('Ed25519')->getPublicKey();
        assert($pub instanceof ECPublicKey);

        return $pub->toString('OpenSSH');
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
