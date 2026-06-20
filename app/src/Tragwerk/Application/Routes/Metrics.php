<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;
use Tragwerk\Application\Middleware\Conditional\RequiresActiveProject;
use Tragwerk\Application\Middleware\Redirect\ToActiveTeam;

final readonly class Metrics
{
    public function __construct(
        private MiddlewareFactory $middlewareFactory,
    ) {
    }

    public function registerRoutes(RouteCollectorInterface $routes): void
    {
        $routes->get(
            '/metrics',
            $this->middlewareFactory->prepare([
                new RequiresActiveProject($this->middlewareFactory->prepare([
                    Handler\Metrics\IndexHandler::class,
                ])),
                ToActiveTeam::class,
            ]),
            'metric',
        );

        $routes->get(
            '/metrics/live',
            $this->middlewareFactory->prepare([
                new RequiresActiveProject($this->middlewareFactory->prepare([
                    Handler\Metrics\LiveHandler::class,
                ])),
                ToActiveTeam::class,
            ]),
            'metric.live',
        );

        $routes->get(
            '/metrics/data',
            $this->middlewareFactory->prepare([
                new RequiresActiveProject($this->middlewareFactory->prepare([
                    Handler\Metrics\DataHandler::class,
                ])),
                ToActiveTeam::class,
            ]),
            'metric.data',
        );
    }
}
