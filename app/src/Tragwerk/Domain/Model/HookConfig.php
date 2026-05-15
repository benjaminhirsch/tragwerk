<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

use Tragwerk\Domain\Enum\HookType;

final readonly class HookConfig
{
    public function __construct(
        public HookType $type,
        public string $value,
    ) {
    }
}
