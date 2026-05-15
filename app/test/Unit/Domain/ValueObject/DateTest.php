<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\ValueObject;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Exception\ValueObject\InvalidTimestamp;
use Tragwerk\Domain\ValueObject\Date;

use function json_encode;

final class DateTest extends TestCase
{
    #[Test]
    public function fromStringCreatesInstance(): void
    {
        $date = Date::fromString('2024-06-15');

        self::assertSame('2024-06-15', $date->toString());
    }

    #[Test]
    public function fromStringThrowsForInvalidDate(): void
    {
        $this->expectException(InvalidTimestamp::class);

        Date::fromString('not-a-date');
    }

    #[Test]
    public function fromDateTimeCreatesInstance(): void
    {
        $dt   = new DateTimeImmutable('2024-06-15 10:30:00');
        $date = Date::fromDateTime($dt);

        self::assertSame('2024-06-15', $date->toString());
    }

    #[Test]
    public function fromDateTimeTruncatesTimeComponent(): void
    {
        $dt   = new DateTimeImmutable('2024-06-15 23:59:59');
        $date = Date::fromDateTime($dt);

        self::assertSame('00:00:00', $date->format('H:i:s'));
    }

    #[Test]
    public function nowReturnsTodaysDate(): void
    {
        $today = Date::now();

        self::assertSame((new DateTimeImmutable())->format('Y-m-d'), $today->toString());
    }

    #[Test]
    public function isEqualToReturnsTrueForSameDate(): void
    {
        $a = Date::fromString('2024-06-15');
        $b = Date::fromString('2024-06-15');

        self::assertTrue($a->isEqualTo($b));
    }

    #[Test]
    public function isEqualToReturnsFalseForDifferentDate(): void
    {
        $a = Date::fromString('2024-06-15');
        $b = Date::fromString('2024-06-16');

        self::assertFalse($a->isEqualTo($b));
    }

    #[Test]
    public function isInPastReturnsTrueForPastDate(): void
    {
        $past = Date::fromString('2020-01-01');

        self::assertTrue($past->isInPast());
    }

    #[Test]
    public function isInFutureReturnsTrueForFutureDate(): void
    {
        $future = Date::fromString('2099-12-31');

        self::assertTrue($future->isInFuture());
    }

    #[Test]
    public function isTodayReturnsTrueForToday(): void
    {
        // BUG: Date::isToday() uses === to compare two DateTimeImmutable objects,
        // which checks reference identity rather than value equality. This always
        // returns false because self::now() creates a new object. Should use ==.
        $today = Date::now();

        self::assertTrue($today->isToday());
    }

    #[Test]
    public function toDateTimeReturnsDateTimeImmutableWithMidnight(): void
    {
        $date = Date::fromString('2024-06-15');
        $dt   = $date->toDateTime();

        self::assertSame('2024-06-15', $dt->format('Y-m-d'));
        self::assertSame('00:00:00', $dt->format('H:i:s'));
    }

    #[Test]
    public function formatDelegatesToUnderlyingDateTime(): void
    {
        $date = Date::fromString('2024-06-15');

        self::assertSame('2024', $date->format('Y'));
        self::assertSame('06', $date->format('m'));
        self::assertSame('15', $date->format('d'));
    }

    #[Test]
    public function stringRepresentationsReturnYmdFormat(): void
    {
        $date = Date::fromString('2024-06-15');

        self::assertSame('2024-06-15', $date->toString());
        self::assertSame('2024-06-15', (string) $date);
        self::assertSame('"2024-06-15"', json_encode($date));
    }
}
