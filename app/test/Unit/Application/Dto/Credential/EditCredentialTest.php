<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Dto\Credential;

use phpseclib3\Crypt\EC;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Dto\Credential\EditCredential;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;

use function array_map;

final class EditCredentialTest extends TestCase
{
    #[Test]
    public function validPemPrivateKeyConstructs(): void
    {
        $dto = new EditCredential('Deploy', 'deploy', self::privateKey());

        self::assertSame('Deploy', $dto->name);
        self::assertTrue($dto->hasNewPrivateKey());
    }

    #[Test]
    public function nullPrivateKeyIsAllowedAndCountsAsNoNewKey(): void
    {
        $dto = new EditCredential('Deploy', 'deploy', null);

        self::assertFalse($dto->hasNewPrivateKey());
    }

    #[Test]
    public function blankPrivateKeyIsAllowedAndCountsAsNoNewKey(): void
    {
        $dto = new EditCredential('Deploy', 'deploy', '   ');

        self::assertFalse($dto->hasNewPrivateKey());
    }

    #[Test]
    public function emptyNameAndUsernameAreRejected(): void
    {
        $fields = $this->errorFields('', '', null);

        self::assertContains('name', $fields);
        self::assertContains('username', $fields);
    }

    #[Test]
    public function invalidPrivateKeyIsRejected(): void
    {
        self::assertContains('privateKey', $this->errorFields('Deploy', 'deploy', 'not-a-key'));
    }

    #[Test]
    public function privilegeDefaultsToRoot(): void
    {
        $dto = new EditCredential('Deploy', 'deploy', null);

        self::assertSame('root', $dto->privilege);
    }

    #[Test]
    public function sudoPrivilegeConstructs(): void
    {
        $dto = new EditCredential('Deploy', 'deploy', null, 'sudo');

        self::assertSame('sudo', $dto->privilege);
    }

    #[Test]
    public function invalidPrivilegeIsRejected(): void
    {
        try {
            new EditCredential('Deploy', 'deploy', null, 'superuser');
        } catch (ValidationCollection $e) {
            $fields = array_map(static fn (ValidationError $v): string => $v->name, $e->validations);
            self::assertContains('privilege', $fields);

            return;
        }

        self::fail('Expected ValidationCollection');
    }

    #[Test]
    public function publicKeyFormatIsRejected(): void
    {
        self::assertContains(
            'privateKey',
            $this->errorFields('Deploy', 'deploy', 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5 test@example.com'),
        );
    }

    /** @return array<string> */
    private function errorFields(string $name, string $username, string|null $key): array
    {
        try {
            new EditCredential($name, $username, $key);
        } catch (ValidationCollection $e) {
            return array_map(static fn (ValidationError $v): string => $v->name, $e->validations);
        }

        self::fail('Expected ValidationCollection');
    }

    private static function privateKey(): string
    {
        return EC::createKey('Ed25519')->toString('OpenSSH');
    }
}
