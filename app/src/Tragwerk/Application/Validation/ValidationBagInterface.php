<?php

declare(strict_types=1);

namespace Tragwerk\Application\Validation;

interface ValidationBagInterface
{
    public function hasErrors(): bool;

    public function getErrorByName(string $name): string|null;

    public function getValueByName(string $name): int|string;
}
