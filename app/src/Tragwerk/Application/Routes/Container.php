<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;
use Tragwerk\Application\Middleware\Conditional\RequiresActiveEnvironment;
use Tragwerk\Application\Middleware\Redirect\ToActiveProject;

final readonly class Container
{
    public function __construct(
        private MiddlewareFactory $middlewareFactory,
    ) {
    }

    public function registerRoutes(RouteCollectorInterface $routes): void
    {
        $routes->get(
            '/containers',
            $this->middlewareFactory->prepare([
                new RequiresActiveEnvironment($this->middlewareFactory->prepare([
                    Handler\Container\IndexHandler::class,
                ])),
                ToActiveProject::class,
            ]),
            'container',
        );
        $routes->get(
            '/containers/status',
            $this->middlewareFactory->prepare([
                new RequiresActiveEnvironment($this->middlewareFactory->prepare([
                    Handler\Container\StatusHandler::class,
                ])),
                ToActiveProject::class,
            ]),
            'container.status',
        );
    }
}
