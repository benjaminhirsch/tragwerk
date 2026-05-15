<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\ValueObject;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Exception\ValueObject\InvalidTimestamp;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function json_encode;

final class TimestampImmutableTest extends TestCase
{
    #[Test]
    #[DoesNotPerformAssertions]
    #[DataProvider('validTimestampStrings')]
    public function fromStringAcceptsAllSupportedFormats(string $raw): void
    {
        TimestampImmutable::fromString($raw);
    }

    /** @return array<string, array{string}> */
    public static function validTimestampStrings(): array
    {
        return [
            'ISO 8601 with microseconds'    => ['2024-01-15T10:30:00.000000+00:00'],
            'ISO 8601 without microseconds' => ['2024-01-15T10:30:00+00:00'],
            'space-separated with micros'   => ['2024-01-15 10:30:00.000000 +00:00'],
            'space-separated no micros'     => ['2024-01-15 10:30:00 +00:00'],
            'space-separated no timezone'   => ['2024-01-15 10:30:00.000000'],
        ];
    }

    #[Test]
    public function fromStringThrowsForInvalidTimestamp(): void
    {
        $this->expectException(InvalidTimestamp::class);

        TimestampImmutable::fromString('not-a-timestamp');
    }

    #[Test]
    public function fromDateTimeWrapsDateTimeImmutable(): void
    {
        $dt = new DateTimeImmutable('2024-06-01 12:00:00');
        $ts = TimestampImmutable::fromDateTime($dt);

        self::assertSame('2024-06-01', $ts->format('Y-m-d'));
    }

    #[Test]
    public function nowReturnsCurrentTime(): void
    {
        $before = new DateTimeImmutable();
        $ts     = TimestampImmutable::now();
        $after  = new DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $ts->toDateTime());
        self::assertLessThanOrEqual($after, $ts->toDateTime());
    }

    #[Test]
    public function isEqualToReturnsTrueForSameTimestamp(): void
    {
        $a = TimestampImmutable::fromString('2024-01-15T10:30:00+00:00');
        $b = TimestampImmutable::fromString('2024-01-15T10:30:00+00:00');

        self::assertTrue($a->isEqualTo($b));
    }

    #[Test]
    public function isEqualToReturnsFalseForDifferentTimestamp(): void
    {
        $a = TimestampImmutable::fromString('2024-01-15T10:30:00+00:00');
        $b = TimestampImmutable::fromString('2024-01-16T10:30:00+00:00');

        self::assertFalse($a->isEqualTo($b));
    }

    #[Test]
    public function isPastReturnsTrueForPastTimestamp(): void
    {
        $past = TimestampImmutable::fromString('2020-01-01T00:00:00+00:00');

        self::assertTrue($past->isPast());
    }

    #[Test]
    public function isPastReturnsFalseForFutureTimestamp(): void
    {
        $future = TimestampImmutable::fromString('2099-12-31T23:59:59+00:00');

        self::assertFalse($future->isPast());
    }

    #[Test]
    public function toDateReturnsDateWithSameDatePart(): void
    {
        $ts   = TimestampImmutable::fromString('2024-06-15T10:30:00+00:00');
        $date = $ts->toDate();

        self::assertSame('2024-06-15', $date->format('Y-m-d'));
    }

    #[Test]
    public function toDateTimeReturnsMatchingDateTime(): void
    {
        $ts = TimestampImmutable::fromString('2024-01-15T10:30:00+00:00');

        self::assertSame('2024-01-15 10:30:00', $ts->toDateTime()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function formatDelegatesToUnderlyingDateTime(): void
    {
        $ts = TimestampImmutable::fromString('2024-06-15T10:30:00+00:00');

        self::assertSame('2024', $ts->format('Y'));
        self::assertSame('06', $ts->format('m'));
        self::assertSame('15', $ts->format('d'));
    }

    #[Test]
    public function stringRepresentationsReturnFormattedTimestamp(): void
    {
        $ts = TimestampImmutable::fromString('2024-06-15 10:30:00.000000 +00:00');

        self::assertSame($ts->toString(), (string) $ts);
        self::assertSame('"' . $ts->toString() . '"', json_encode($ts));
    }
}
