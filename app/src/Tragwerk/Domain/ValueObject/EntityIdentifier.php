<?php

declare(strict_types=1);

namespace Tragwerk\Domain\ValueObject;

use Stringable;
use Tragwerk\Domain\Enum\EntityType;

/** @pure */
interface EntityIdentifier extends ValueObject, Stringable
{
    public static function getEntityType(): EntityType;

    /** @phpstan-pure */
    public function toString(): string;
}
