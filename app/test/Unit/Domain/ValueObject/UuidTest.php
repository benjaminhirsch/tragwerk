<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\ValueObject;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Exception\ValueObject\InvalidIdentifier;
use Tragwerk\Domain\ValueObject\Uuid;

use function json_encode;

final class UuidTest extends TestCase
{
    private const string VALID_UUID     = '550e8400-e29b-41d4-a716-446655440000';
    private const string VALID_UUID_ALT = '7f000001-0000-4000-8000-000000000000';
    private const string NIL_UUID       = '00000000-0000-0000-0000-000000000000';

    #[Test]
    public function fromStringCreatesInstance(): void
    {
        $uuid = Uuid::fromString(self::VALID_UUID);

        self::assertSame(self::VALID_UUID, $uuid->toString());
    }

    #[Test]
    public function fromStringThrowsForInvalidUuid(): void
    {
        $this->expectException(InvalidIdentifier::class);

        Uuid::fromString('not-a-uuid');
    }

    #[Test]
    #[DataProvider('validUuids')]
    public function isValidReturnsTrueForValidUuids(string $uuid): void
    {
        self::assertTrue(Uuid::isValid($uuid));
    }

    /** @return array<string, array{string}> */
    public static function validUuids(): array
    {
        return [
            'uuid4' => [self::VALID_UUID],
            'nil'   => [self::NIL_UUID],
        ];
    }

    #[Test]
    #[DataProvider('invalidUuids')]
    public function isValidReturnsFalseForInvalidUuids(string $uuid): void
    {
        self::assertFalse(Uuid::isValid($uuid));
    }

    /** @return array<string, array{string}> */
    public static function invalidUuids(): array
    {
        return [
            'empty'          => [''],
            'random string'  => ['not-a-uuid'],
            'missing dashes' => ['550e8400e29b41d4a716446655440000'],
        ];
    }

    #[Test]
    public function nilReturnsNilUuid(): void
    {
        $nil = Uuid::nil();

        self::assertSame(self::NIL_UUID, $nil->toString());
    }

    #[Test]
    public function createReturnsValidUuid(): void
    {
        $uuid = Uuid::create();

        self::assertTrue(Uuid::isValid($uuid->toString()));
    }

    #[Test]
    public function createRandomReturnsValidUuid(): void
    {
        $uuid = Uuid::createRandom();

        self::assertTrue(Uuid::isValid($uuid->toString()));
    }

    #[Test]
    public function eachCreateCallReturnsDifferentUuid(): void
    {
        $a = Uuid::create();
        $b = Uuid::create();

        self::assertFalse($a->isEqualTo($b));
    }

    #[Test]
    public function isEqualToReturnsTrueForSameUuid(): void
    {
        $a = Uuid::fromString(self::VALID_UUID);
        $b = Uuid::fromString(self::VALID_UUID);

        self::assertTrue($a->isEqualTo($b));
    }

    #[Test]
    public function isEqualToReturnsFalseForDifferentUuid(): void
    {
        $a = Uuid::fromString(self::VALID_UUID);
        $b = Uuid::fromString(self::VALID_UUID_ALT);

        self::assertFalse($a->isEqualTo($b));
    }

    #[Test]
    public function stringRepresentationsReturnRawId(): void
    {
        $uuid = Uuid::fromString(self::VALID_UUID);

        self::assertSame(self::VALID_UUID, $uuid->toString());
        self::assertSame(self::VALID_UUID, (string) $uuid);
        self::assertSame('"' . self::VALID_UUID . '"', json_encode($uuid));
    }
}
