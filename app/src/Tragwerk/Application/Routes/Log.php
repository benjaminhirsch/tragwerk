<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;
use Tragwerk\Application\Middleware\Conditional\RequiresActiveEnvironment;
use Tragwerk\Application\Middleware\Redirect\ToActiveProject;

final readonly class Log
{
    public function __construct(
        private MiddlewareFactory $middlewareFactory,
    ) {
    }

    public function registerRoutes(RouteCollectorInterface $routes): void
    {
        $routes->get(
            '/logs',
            $this->middlewareFactory->prepare([
                new RequiresActiveEnvironment($this->middlewareFactory->prepare([
                    Handler\Log\IndexHandler::class,
                ])),
                ToActiveProject::class,
            ]),
            'log',
        );
        $routes->get(
            '/logs/tail',
            $this->middlewareFactory->prepare([
                new RequiresActiveEnvironment($this->middlewareFactory->prepare([
                    Handler\Log\TailHandler::class,
                ])),
                ToActiveProject::class,
            ]),
            'log.tail',
        );
    }
}
