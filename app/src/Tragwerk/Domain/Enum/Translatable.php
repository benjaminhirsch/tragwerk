<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Enum;

interface Translatable
{
    public function translatableName(): string;
}
