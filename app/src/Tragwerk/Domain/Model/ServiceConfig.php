<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

use Tragwerk\Domain\Enum\ServiceRuntime;

final readonly class ServiceConfig
{
    public function __construct(
        public string $name,
        public ServiceRuntime $type,
        public int|null $disk = null,
        public int|null $localPort = null,
    ) {
    }
}
