<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

final readonly class ExtensionConfig
{
    public function __construct(public string $name)
    {
    }
}
