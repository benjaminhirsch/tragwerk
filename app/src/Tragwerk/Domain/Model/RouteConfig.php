<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

use Tragwerk\Domain\Enum\RouteType;

final readonly class RouteConfig
{
    public function __construct(
        public string $pattern,
        public RouteType $type,
        public string|null $upstream = null,
        public string|null $to = null,
    ) {
    }
}
