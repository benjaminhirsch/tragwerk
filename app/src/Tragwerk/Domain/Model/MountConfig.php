<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

use Tragwerk\Domain\Enum\MountSource;

final readonly class MountConfig
{
    public function __construct(
        public string $name,
        public MountSource $source,
        public string $path,
        public bool $cloneFromParent = true,
    ) {
    }
}
