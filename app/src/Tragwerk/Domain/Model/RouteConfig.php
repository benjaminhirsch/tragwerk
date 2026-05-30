<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

final readonly class RouteConfig
{
    public function __construct(
        public string $pattern,
        public string|null $upstream = null,
    ) {
    }
}
