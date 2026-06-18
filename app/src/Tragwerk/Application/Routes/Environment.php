<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;
use Tragwerk\Application\Middleware\Conditional\RequiresActiveProject;
use Tragwerk\Application\Middleware\Redirect\ToActiveTeam;

final readonly class Environment
{
    public function __construct(
        private MiddlewareFactory $middlewareFactory,
    ) {
    }

    public function registerRoutes(RouteCollectorInterface $routes): void
    {
        $routes->get(
            '/environments',
            $this->middlewareFactory->prepare([
                new RequiresActiveProject($this->middlewareFactory->prepare([
                    Handler\Environment\IndexHandler::class,
                ])),
                ToActiveTeam::class,
            ]),
            'environment',
        );

        $routes->get(
            '/environments/show',
            $this->middlewareFactory->prepare([Handler\Environment\ShowHandler::class]),
            'environment.show',
        );
    }
}
