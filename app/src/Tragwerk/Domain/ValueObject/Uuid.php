<?php

declare(strict_types=1);

namespace Tragwerk\Domain\ValueObject;

use Override;
use Stringable;
use Tragwerk\Domain\Exception\ValueObject\InvalidIdentifier;

use function sprintf;

readonly class Uuid implements ValueObject, Stringable
{
    final public function __construct(
        private string $id,
    ) {
    }

    /** @psalm-mutation-free */
    public static function create(): static
    {
        return new static(\Ramsey\Uuid\Uuid::uuid7()->toString());
    }

    /** @psalm-mutation-free */
    public static function createRandom(): static
    {
        return new static(\Ramsey\Uuid\Uuid::uuid4()->toString());
    }

    /** @pure */
    public static function fromString(string $id): static
    {
        if (! static::isValid($id)) {
            throw new InvalidIdentifier(sprintf('Given value: `%s` is no valid UUID', $id));
        }

        return new static($id);
    }

    /** @pure */
    public static function isValid(string $id): bool
    {
        return \Ramsey\Uuid\Uuid::isValid($id);
    }

    /** @pure */
    #[Override]
    public function isEqualTo(ValueObject $valueObject): bool
    {
        return $valueObject instanceof static && $this->id === $valueObject->id;
    }

    #[Override]
    public function jsonSerialize(): string
    {
        return (string) $this;
    }

    /** @pure */
    public function toString(): string
    {
        return (string) $this;
    }

    /** @pure */
    #[Override]
    public function __toString(): string
    {
        return $this->id;
    }

    public static function nil(): static
    {
        return new static(\Ramsey\Uuid\Uuid::NIL);
    }
}
