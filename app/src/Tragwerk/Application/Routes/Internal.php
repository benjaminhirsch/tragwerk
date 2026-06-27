<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Fig\Http\Message\RequestMethodInterface;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;
use Tragwerk\Application\Middleware\AuthenticationMiddleware;

final readonly class Internal
{
    public function __construct(
        private MiddlewareFactory $middlewareFactory,
    ) {
    }

    public function registerRoutes(RouteCollectorInterface $routes): void
    {
        $routes->route(
            '/internal/git-auth',
            $this->middlewareFactory->prepare([Handler\Internal\GitAuthHandler::class]),
            [RequestMethodInterface::METHOD_POST],
            'internal.git-auth',
        )->setOptions([AuthenticationMiddleware::OPTION_REQUIRE_AUTHENTICATION => false]);
    }
}
