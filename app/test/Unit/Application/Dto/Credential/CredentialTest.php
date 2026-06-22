<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Dto\Credential;

use phpseclib3\Crypt\EC;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Dto\Credential\Credential;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;

use function array_map;

final class CredentialTest extends TestCase
{
    #[Test]
    public function validPemPrivateKeyConstructs(): void
    {
        $dto = new Credential('Deploy', 'deploy', self::privateKey());

        self::assertSame('Deploy', $dto->name);
        self::assertSame('deploy', $dto->username);
    }

    #[Test]
    public function emptyNameAndUsernameAreRejected(): void
    {
        $fields = $this->errorFields('', '', self::privateKey());

        self::assertContains('name', $fields);
        self::assertContains('username', $fields);
    }

    #[Test]
    public function missingPrivateKeyIsRejected(): void
    {
        self::assertContains('privateKey', $this->errorFields('Deploy', 'deploy', null));
    }

    #[Test]
    public function invalidPrivateKeyIsRejected(): void
    {
        self::assertContains('privateKey', $this->errorFields('Deploy', 'deploy', 'not-a-key'));
    }

    /** @return array<string> */
    private function errorFields(string $name, string $username, string|null $key): array
    {
        try {
            new Credential($name, $username, $key);
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
