<?php

declare(strict_types=1);

namespace Tragwerk\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Stringable;
use Throwable;
use Tragwerk\Domain\Exception\ValueObject\InvalidTimestamp;

use function assert;
use function sprintf;

final readonly class Date implements ValueObject, Stringable
{
    public const string FORMAT = 'Y-m-d';

    private function __construct(
        private DateTimeImmutable $timestamp,
    ) {
    }

    public static function now(): self
    {
        return new self(new DateTimeImmutable()->setTime(0, 0));
    }

    /** @phpstan-pure */
    public static function fromString(string $rawDate): self
    {
        try {
            $dateTimeImmutable = DateTimeImmutable::createFromFormat('Y-m-d', $rawDate);
            assert($dateTimeImmutable instanceof DateTimeImmutable);
            $dateTimeImmutable = $dateTimeImmutable->setTime(0, 0);
            assert($dateTimeImmutable instanceof DateTimeImmutable);

            return new self($dateTimeImmutable);
        } catch (Throwable $e) {
            throw new InvalidTimestamp(
                sprintf('Unable to create timestamp, invalid string. Reason: %s', $e->getMessage()),
                previous: $e,
            );
        }
    }

    /** @phpstan-pure */
    public static function fromDateTime(DateTimeInterface $dateTime): self
    {
        try {
            $dateTimeImmutable = DateTimeImmutable::createFromInterface($dateTime);
            $dateTimeImmutable = $dateTimeImmutable->setTime(0, 0);
            assert($dateTimeImmutable instanceof DateTimeImmutable);

            return new self($dateTimeImmutable);
        } catch (Throwable $e) {
            throw new InvalidTimestamp(
                sprintf('Unable to create timestamp, invalid string. Reason: %s', $e->getMessage()),
                previous: $e,
            );
        }
    }

    /** @phpstan-pure */
    public function format(string $format): string
    {
        return $this->timestamp->format($format);
    }

    /** @phpstan-pure */
    #[Override]
    public function isEqualTo(ValueObject $valueObject): bool
    {
        return $valueObject instanceof self && (string) $this === (string) $valueObject;
    }

    public function isToday(): bool
    {
        return (string) $this === (string) self::now();
    }

    public function isInPast(): bool
    {
        return $this->toDateTime() < self::now()->toDateTime();
    }

    public function isInFuture(): bool
    {
        return $this->toDateTime() > self::now()->toDateTime();
    }

    /** @phpstan-pure */
    public function toDateTime(): DateTimeImmutable
    {
        return $this->timestamp;
    }

    #[Override]
    public function jsonSerialize(): string
    {
        return (string) $this;
    }

    /** @phpstan-pure */
    public function toString(): string
    {
        return (string) $this;
    }

    /** @phpstan-pure */
    #[Override]
    public function __toString(): string
    {
        return $this->format(self::FORMAT);
    }
}
