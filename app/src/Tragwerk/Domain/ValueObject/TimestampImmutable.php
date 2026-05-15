<?php

declare(strict_types=1);

namespace Tragwerk\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Stringable;
use Throwable;
use Tragwerk\Domain\Exception\ValueObject\InvalidTimestamp;

use function sprintf;

final readonly class TimestampImmutable implements ValueObject, Stringable
{
    public const string FORMAT = 'Y-m-d H:i:s.u P';

    private function __construct(
        private DateTimeImmutable $timestamp,
    ) {
    }

    public static function now(): self
    {
        return new self(new DateTimeImmutable());
    }

    /** @phpstan-pure */
    public static function fromString(string $rawTimestamp): self
    {
        $formats = [
            'Y-m-d\TH:i:s.uP',
            'Y-m-d\TH:i:sP',
            'Y-m-d H:i:s.u P',
            'Y-m-d H:i:s P',
            'Y-m-d H:i:s.u',
        ];

        foreach ($formats as $format) {
            try {
                $dateTimeImmutable = DateTimeImmutable::createFromFormat($format, $rawTimestamp);
            } catch (Throwable) {
                continue;
            }

            if ($dateTimeImmutable === false) {
                continue;
            }

            return new self($dateTimeImmutable);
        }

        throw new InvalidTimestamp(sprintf('Unable to create timestamp, invalid string: %s', $rawTimestamp));
    }

    /** @phpstan-pure */
    public static function fromDateTime(DateTimeInterface $dateTime): self
    {
        $dateTimeImmutable = DateTimeImmutable::createFromInterface($dateTime);

        return new self($dateTimeImmutable);
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

    /** @phpstan-pure */
    public function toDateTime(): DateTimeImmutable
    {
        return $this->timestamp;
    }

    /** @phpstan-pure */
    public function toDate(): Date
    {
        return Date::fromDateTime($this->toDateTime());
    }

    /** @psalm-mutation-free */
    public function isPast(): bool
    {
        return $this->timestamp < new DateTimeImmutable();
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
