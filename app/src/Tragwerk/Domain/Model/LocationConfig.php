<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

final readonly class LocationConfig
{
    public function __construct(
        public string $path,
        public string $root,
        public string $index = 'index.php',
        public string|null $passthru = null,
    ) {
    }
}
