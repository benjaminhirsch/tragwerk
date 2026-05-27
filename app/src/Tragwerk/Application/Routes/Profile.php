<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Fig\Http\Message\RequestMethodInterface;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;

final readonly class Profile
{
    public function __construct(
        private MiddlewareFactory $middlewareFactory,
    ) {
    }

    public function registerRoutes(RouteCollectorInterface $routes): void
    {
        $routes->route(
            '/profile',
            $this->middlewareFactory->prepare([Handler\Profile\IndexHandler::class]),
            [RequestMethodInterface::METHOD_GET, RequestMethodInterface::METHOD_POST],
            'profile',
        );
    }
}
