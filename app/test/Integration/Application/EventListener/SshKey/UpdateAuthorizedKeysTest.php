<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\EventListener\SshKey;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Application\EventListener\SshKey\UpdateAuthorizedKeys;
use Tragwerk\Domain\Entity\SshKey;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\SshKeyRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\SshKeyIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\IntegrationTestCase;

use function array_filter;
use function array_values;
use function assert;
use function explode;
use function file_get_contents;
use function str_contains;
use function str_starts_with;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class UpdateAuthorizedKeysTest extends IntegrationTestCase
{
    private const string PUBLIC_KEY = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIM1N key@test';

    private string $path;
    private SshKeyRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $path = tempnam(sys_get_temp_dir(), 'authorized_keys');
        assert($path !== false);
        $this->path = $path;

        $repository = $this->container->get(SshKeyRepository::class);
        assert($repository instanceof SshKeyRepository);
        $this->repository = $repository;
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    #[Test]
    public function emitsForcedCommandPerKey(): void
    {
        $key = $this->seedKey('forced@example.com');

        $this->invokeListener();

        $lines = $this->lines();

        $expectedPrefix = 'command="/usr/local/bin/git-auth-wrapper ' . $key->id->toString() . '"';

        self::assertCount(1, $lines);
        self::assertTrue(str_starts_with($lines[0], $expectedPrefix));
        self::assertStringContainsString('no-pty', $lines[0]);
        self::assertStringContainsString(self::PUBLIC_KEY, $lines[0]);
    }

    #[Test]
    public function writesOneLinePerKeyWithItsOwnId(): void
    {
        $keyA = $this->seedKey('a@example.com');
        $keyB = $this->seedKey('b@example.com');

        $this->invokeListener();

        $lines = $this->lines();

        self::assertCount(2, $lines);

        $joined = $lines[0] . "\n" . $lines[1];
        self::assertStringContainsString($keyA->id->toString(), $joined);
        self::assertStringContainsString($keyB->id->toString(), $joined);

        foreach ($lines as $line) {
            self::assertTrue(str_contains($line, 'git-auth-wrapper'));
        }
    }

    private function invokeListener(): void
    {
        new UpdateAuthorizedKeys($this->repository, $this->path)();
    }

    /** @return list<string> */
    private function lines(): array
    {
        $contents = file_get_contents($this->path);
        assert($contents !== false);

        return array_values(array_filter(
            explode("\n", $contents),
            static fn (string $line): bool => $line !== '',
        ));
    }

    private function seedKey(string $email): SshKey
    {
        $now            = TimestampImmutable::now();
        $user           = new User(
            UserIdentifier::create(),
            $email,
            'Ssh',
            'Tester',
            PasswordHash::create('password123'),
            $now,
            $now,
        );
        $userRepository = $this->container->get(UserRepository::class);
        assert($userRepository instanceof UserRepository);
        $userRepository->create($user);

        $key = new SshKey(
            SshKeyIdentifier::create(),
            $user->id,
            'test-key',
            self::PUBLIC_KEY,
            $now,
        );
        $this->repository->create($key);

        return $key;
    }
}
