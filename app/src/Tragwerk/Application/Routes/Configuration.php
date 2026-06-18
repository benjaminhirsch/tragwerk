<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;
use Tragwerk\Application\Middleware\Conditional\RequiresActiveProject;
use Tragwerk\Application\Middleware\Redirect\ToActiveTeam;

final readonly class Configuration
{
    public function __construct(
        private MiddlewareFactory $middlewareFactory,
    ) {
    }

    public function registerRoutes(RouteCollectorInterface $routes): void
    {
        $routes->get(
            '/configurations',
            $this->middlewareFactory->prepare([
                new RequiresActiveProject($this->middlewareFactory->prepare([
                    Handler\Configuration\IndexHandler::class,
                ])),
                ToActiveTeam::class,
            ]),
            'configuration',
        );

        $routes->get(
            '/configurations/mounts',
            $this->middlewareFactory->prepare([
                new RequiresActiveProject($this->middlewareFactory->prepare([
                    Handler\Configuration\MountSizesHandler::class,
                ])),
                ToActiveTeam::class,
            ]),
            'configuration.mounts',
        );
    }
}
