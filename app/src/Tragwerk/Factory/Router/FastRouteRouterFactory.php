<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Router;

use Mezzio\Router\FastRouteRouter;

final readonly class FastRouteRouterFactory
{
    public function __invoke(): FastRouteRouter
    {
        return new FastRouteRouter();
    }
}
