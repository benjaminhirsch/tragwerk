<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

final readonly class PhpSettingConfig
{
    public function __construct(public string $name, public string $value)
    {
    }
}
