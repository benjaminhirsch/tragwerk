<?php

declare(strict_types=1);

namespace Tragwerk\Domain\ValueObject;

use JsonSerializable;

interface ValueObject extends JsonSerializable
{
    public function isEqualTo(ValueObject $valueObject): bool;
}
