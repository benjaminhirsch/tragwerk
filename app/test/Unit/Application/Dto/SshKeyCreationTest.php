<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Dto;

use phpseclib3\Crypt\EC;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Dto\SshKeyCreation;
use Tragwerk\Application\Exception\ValidationError;

final class SshKeyCreationTest extends TestCase
{
    #[Test]
    public function validPublicKeyConstructs(): void
    {
        $dto = new SshKeyCreation('Laptop', self::publicKey());

        self::assertSame('Laptop', $dto->name);
    }

    #[Test]
    public function emptyNameIsRejected(): void
    {
        try {
            new SshKeyCreation('', self::publicKey());
        } catch (ValidationError $e) {
            self::assertSame('name', $e->name);

            return;
        }

        self::fail('Expected ValidationError');
    }

    #[Test]
    public function invalidPublicKeyIsRejected(): void
    {
        try {
            new SshKeyCreation('Laptop', 'not-a-key');
        } catch (ValidationError $e) {
            self::assertSame('publicKey', $e->name);

            return;
        }

        self::fail('Expected ValidationError');
    }

    private static function publicKey(): string
    {
        // phpseclib types getPublicKey() loosely; the OpenSSH export is a string.
        return EC::createKey('Ed25519')->getPublicKey()->toString('OpenSSH'); // @phpstan-ignore method.nonObject, return.type
    }
}
