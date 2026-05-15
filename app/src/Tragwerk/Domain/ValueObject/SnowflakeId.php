<?php

declare(strict_types=1);

namespace Tragwerk\Domain\ValueObject;

use Godruoyi\Snowflake\Snowflake as SnowflakeGenerator;
use Override;
use Stringable;
use Tragwerk\Domain\Exception\ValueObject\InvalidSnowflakeId;

use function ctype_digit;
use function sprintf;
use function strlen;

/**
 * 63-bit time-sorted identifier generated via the Twitter Snowflake algorithm.
 *
 * Structure (64 bits, MSB always 0):
 *   [0][41-bit timestamp ms][5-bit datacenter][5-bit worker][12-bit sequence]
 *
 * IDs are lexicographically sortable as strings because they share the same
 * bit-length range — use isBefore()/isAfter() for explicit ordering.
 */
final readonly class SnowflakeId implements ValueObject, Stringable
{
    /** Maximum value of a 63-bit unsigned integer (PHP_INT_MAX on 64-bit). */
    private const string MAX = '9223372036854775807';

    public function __construct(
        private string $id,
    ) {
    }

    /**
     * Generate a new Snowflake ID using the provided generator.
     *
     * Inject a pre-configured {@see SnowflakeGenerator} with a stable
     * datacenter/worker pair so IDs stay unique across nodes.
     */
    public static function generate(SnowflakeGenerator $generator): static
    {
        return new static($generator->id());
    }

    /**
     * Reconstruct a SnowflakeId from a previously persisted string.
     *
     * @throws InvalidSnowflakeId
     */
    public static function fromString(string $id): static
    {
        if (! static::isValid($id)) {
            throw new InvalidSnowflakeId(
                sprintf('"%s" is not a valid Snowflake ID', $id),
            );
        }

        return new static($id);
    }

    /**
     * A valid Snowflake ID is a non-zero decimal string in the range [1, 2^63-1].
     *
     * @pure
     */
    public static function isValid(string $id): bool
    {
        if (! ctype_digit($id) || $id === '0') {
            return false;
        }

        $len = strlen($id);

        if ($len > 19) {
            return false;
        }

        // For 19-digit values, compare lexicographically against PHP_INT_MAX.
        // Same length → lexicographic order equals numeric order.
        return $len !== 19 || $id <= self::MAX;
    }

    /** @pure */
    #[Override]
    public function isEqualTo(ValueObject $valueObject): bool
    {
        return $valueObject instanceof static && $this->id === $valueObject->id;
    }

    /**
     * Returns true when this ID was generated before $other.
     *
     * Snowflake IDs embed a millisecond timestamp in the top bits, so a
     * lower numeric value means an earlier generation time.
     *
     * @pure
     */
    public function isBefore(self $other): bool
    {
        return $this->toInt() < $other->toInt();
    }

    /**
     * Returns true when this ID was generated after $other.
     *
     * @pure
     */
    public function isAfter(self $other): bool
    {
        return $this->toInt() > $other->toInt();
    }

    /**
     * The raw 63-bit integer value.
     *
     * Safe on 64-bit PHP where PHP_INT_MAX = 2^63-1.
     *
     * @pure
     */
    public function toInt(): int
    {
        return (int) $this->id;
    }

    /** @pure */
    public function toString(): string
    {
        return $this->id;
    }

    #[Override]
    public function jsonSerialize(): string
    {
        return $this->id;
    }

    /** @pure */
    #[Override]
    public function __toString(): string
    {
        return $this->id;
    }
}
