<?php

declare(strict_types=1);

namespace Tragwerk\Application\Exception;

use RuntimeException;

final class Validation extends RuntimeException
{
    private function __construct(public string $validationField, public string $validationMessage)
    {
        parent::__construct($this->validationMessage);
    }

    public static function make(string $field, string $message): self
    {
        return new self($field, $message);
    }
}
