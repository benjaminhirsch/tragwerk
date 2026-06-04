<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Profile;

use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\EC\PublicKey as ECPublicKey;
use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\SshKey;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\SshKeyRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\SshKeyIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;
use function iterator_to_array;

final class ProfileHandlerTest extends AppIntegrationTestCase
{
    private const string EMAIL    = 'profile-test@example.com';
    private const string PASSWORD = 'secure-password-123';

    private User $user;
    private string $sessionCookie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->seedUser();
        $this->seedTeam();
        $this->sessionCookie = $this->loginAndGetCookie();
    }

    #[Test]
    public function getRendersProfilePage(): void
    {
        $response = $this->dispatch('GET', $this->url('profile'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function getRedirectsUnauthenticatedUserToLogin(): void
    {
        $response = $this->dispatch('GET', $this->url('profile'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('login'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function createSshKeyWithValidDataRedirectsToProfile(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('profile'),
            ['name' => 'My Key', 'publicKey' => self::makePublicKey()],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('profile'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function createSshKeyPersistsKeyInDatabase(): void
    {
        $publicKey = self::makePublicKey();

        $this->dispatch(
            'POST',
            $this->url('profile'),
            ['name' => 'Deploy Key', 'publicKey' => $publicKey],
            $this->sessionCookie,
        );

        $repository = $this->container->get(SshKeyRepository::class);
        assert($repository instanceof SshKeyRepository);

        $keys = iterator_to_array($repository->getByUserId($this->user->id), false);
        self::assertCount(1, $keys);
        self::assertSame('Deploy Key', $keys[0]->name);
    }

    #[Test]
    public function createSshKeyWithEmptyNameReRendersForm(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('profile'),
            ['name' => '', 'publicKey' => self::makePublicKey()],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createSshKeyWithInvalidKeyReRendersForm(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('profile'),
            ['name' => 'My Key', 'publicKey' => 'not-a-valid-key'],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createSshKeyWithPrivateKeyReRendersForm(): void
    {
        $privateKey = EC::createKey('Ed25519')->toString('OpenSSH');

        $response = $this->dispatch(
            'POST',
            $this->url('profile'),
            ['name' => 'My Key', 'publicKey' => $privateKey],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function deleteSshKeyRedirectsToProfile(): void
    {
        $key      = $this->seedSshKey();
        $response = $this->dispatch(
            'POST',
            $this->url('profile'),
            ['action' => 'delete', 'keyId' => $key->id->toString()],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('profile'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function deleteSshKeyRemovesKeyFromDatabase(): void
    {
        $key = $this->seedSshKey();
        $this->dispatch(
            'POST',
            $this->url('profile'),
            ['action' => 'delete', 'keyId' => $key->id->toString()],
            $this->sessionCookie,
        );

        $repository = $this->container->get(SshKeyRepository::class);
        assert($repository instanceof SshKeyRepository);

        $remaining = iterator_to_array($repository->getByUserId($this->user->id), false);
        self::assertCount(0, $remaining);
    }

    #[Test]
    public function deleteWithUnknownKeyIdRedirectsToProfile(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('profile'),
            ['action' => 'delete', 'keyId' => SshKeyIdentifier::create()->toString()],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('profile'), $response->getHeaderLine('Location'));
    }

    private function seedUser(): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            self::EMAIL,
            'Profile',
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

    private function seedTeam(): Team
    {
        $now  = TimestampImmutable::now();
        $team = new Team(
            TeamIdentifier::create(),
            'Test Team',
            $this->user->id,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
        );

        $repository = $this->container->get(TeamRepository::class);
        assert($repository instanceof TeamRepository);
        $repository->create($team);
        $repository->assignUsers($team->id, [$this->user->id]);

        return $team;
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
