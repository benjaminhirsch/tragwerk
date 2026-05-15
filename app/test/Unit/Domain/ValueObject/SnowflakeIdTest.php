<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\ValueObject;

use Godruoyi\Snowflake\Snowflake as SnowflakeGenerator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Exception\ValueObject\InvalidSnowflakeId;
use Tragwerk\Domain\ValueObject\SnowflakeId;
use Tragwerk\Domain\ValueObject\Uuid;

use function json_encode;

final class SnowflakeIdTest extends TestCase
{
    #[Test]
    public function fromStringCreatesInstance(): void
    {
        $id = SnowflakeId::fromString('1234567890');

        self::assertSame('1234567890', $id->toString());
    }

    #[Test]
    public function fromStringThrowsForInvalidId(): void
    {
        $this->expectException(InvalidSnowflakeId::class);
        $this->expectExceptionMessage('"invalid" is not a valid Snowflake ID');

        SnowflakeId::fromString('invalid');
    }

    #[Test]
    #[DataProvider('validIds')]
    public function isValidReturnsTrueForValidIds(string $id): void
    {
        self::assertTrue(SnowflakeId::isValid($id));
    }

    /** @return array<string, array{string}> */
    public static function validIds(): array
    {
        return [
            'minimum'    => ['1'],
            'typical'    => ['123456789012345'],
            'max 63-bit' => ['9223372036854775807'],
        ];
    }

    #[Test]
    #[DataProvider('invalidIds')]
    public function isValidReturnsFalseForInvalidIds(string $id): void
    {
        self::assertFalse(SnowflakeId::isValid($id));
    }

    /** @return array<string, array{string}> */
    public static function invalidIds(): array
    {
        return [
            'zero'        => ['0'],
            'empty'       => [''],
            'negative'    => ['-1'],
            'non-numeric' => ['abc'],
            'too long'    => ['12345678901234567890'],
            'exceeds max' => ['9223372036854775808'],
        ];
    }

    #[Test]
    public function isEqualToReturnsTrueForSameId(): void
    {
        $a = SnowflakeId::fromString('1000000000');
        $b = SnowflakeId::fromString('1000000000');

        self::assertTrue($a->isEqualTo($b));
    }

    #[Test]
    public function isEqualToReturnsFalseForDifferentId(): void
    {
        $a = SnowflakeId::fromString('1000000000');
        $b = SnowflakeId::fromString('2000000000');

        self::assertFalse($a->isEqualTo($b));
    }

    #[Test]
    public function isEqualToReturnsFalseForDifferentValueObjectType(): void
    {
        $snowflake = SnowflakeId::fromString('1000000000');
        $uuid      = Uuid::fromString('550e8400-e29b-41d4-a716-446655440000');

        self::assertFalse($snowflake->isEqualTo($uuid));
    }

    #[Test]
    public function isBeforeAndIsAfterOrderByValue(): void
    {
        $earlier = SnowflakeId::fromString('100');
        $later   = SnowflakeId::fromString('200');

        self::assertTrue($earlier->isBefore($later));
        self::assertFalse($later->isBefore($earlier));
        self::assertTrue($later->isAfter($earlier));
        self::assertFalse($earlier->isAfter($later));
    }

    #[Test]
    public function toIntReturnsIntegerRepresentation(): void
    {
        $id = SnowflakeId::fromString('9999999999');

        self::assertSame(9999999999, $id->toInt());
    }

    #[Test]
    public function stringRepresentationsReturnRawId(): void
    {
        $id = SnowflakeId::fromString('1234567890');

        self::assertSame('1234567890', $id->toString());
        self::assertSame('1234567890', (string) $id);
        self::assertSame('"1234567890"', json_encode($id));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function generateDelegatesToSnowflakeGenerator(): void
    {
        $generator = $this->createMock(SnowflakeGenerator::class);
        $generator->method('id')->willReturn('9876543210');

        $id = SnowflakeId::generate($generator);

        self::assertSame('9876543210', $id->toString());
    }
}
