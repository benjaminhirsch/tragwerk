<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\ValueObject;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Exception\ValueObject\InvalidPasswordHash;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\Uuid;

use function json_decode;
use function json_encode;
use function password_hash;

use const PASSWORD_BCRYPT;

final class PasswordHashTest extends TestCase
{
    private const string RAW_PASSWORD = 'correct-horse-battery-staple';

    #[Test]
    public function createHashesPassword(): void
    {
        $hash = PasswordHash::create(self::RAW_PASSWORD);

        self::assertTrue($hash->verify(self::RAW_PASSWORD));
    }

    #[Test]
    public function verifyReturnsFalseForWrongPassword(): void
    {
        $hash = PasswordHash::create(self::RAW_PASSWORD);

        self::assertFalse($hash->verify('wrong-password'));
    }

    #[Test]
    public function createProducesDifferentHashesDueToSalt(): void
    {
        $a = PasswordHash::create(self::RAW_PASSWORD);
        $b = PasswordHash::create(self::RAW_PASSWORD);

        self::assertNotSame($a->toString(), $b->toString());
    }

    #[Test]
    public function generateRandomCreatesVerifiableHash(): void
    {
        $hash = PasswordHash::generateRandom();

        self::assertTrue(PasswordHash::isValidHash($hash->toString()));
    }

    #[Test]
    public function isValidHashReturnsTrueForArgon2idHash(): void
    {
        $hash = PasswordHash::create(self::RAW_PASSWORD);

        self::assertTrue(PasswordHash::isValidHash($hash->toString()));
    }

    #[Test]
    public function isValidHashReturnsFalseForPlainText(): void
    {
        self::assertFalse(PasswordHash::isValidHash(self::RAW_PASSWORD));
    }

    #[Test]
    public function isValidHashReturnsFalseForBcryptHash(): void
    {
        $bcrypt = password_hash(self::RAW_PASSWORD, PASSWORD_BCRYPT);

        self::assertFalse(PasswordHash::isValidHash($bcrypt));
    }

    #[Test]
    public function fromHashCreatesInstanceFromValidHash(): void
    {
        $original = PasswordHash::create(self::RAW_PASSWORD);
        $restored = PasswordHash::fromHash($original->toString());

        self::assertTrue($restored->verify(self::RAW_PASSWORD));
    }

    #[Test]
    public function fromHashThrowsForInvalidHash(): void
    {
        $this->expectException(InvalidPasswordHash::class);

        PasswordHash::fromHash('not-a-hash');
    }

    #[Test]
    public function isEqualToReturnsTrueForSameHashString(): void
    {
        $hash  = PasswordHash::create(self::RAW_PASSWORD);
        $clone = PasswordHash::fromHash($hash->toString());

        self::assertTrue($hash->isEqualTo($clone));
    }

    #[Test]
    public function isEqualToReturnsFalseForDifferentHashString(): void
    {
        $a = PasswordHash::create(self::RAW_PASSWORD);
        $b = PasswordHash::create(self::RAW_PASSWORD);

        self::assertFalse($a->isEqualTo($b));
    }

    #[Test]
    public function isEqualToReturnsFalseForDifferentValueObjectType(): void
    {
        $hash = PasswordHash::create(self::RAW_PASSWORD);
        $uuid = Uuid::fromString('550e8400-e29b-41d4-a716-446655440000');

        self::assertFalse($hash->isEqualTo($uuid));
    }

    #[Test]
    public function stringRepresentationsReturnRawHash(): void
    {
        $hash = PasswordHash::create(self::RAW_PASSWORD);

        self::assertSame($hash->toString(), (string) $hash);
        self::assertSame($hash->toString(), json_decode((string) json_encode($hash)));
    }
}
