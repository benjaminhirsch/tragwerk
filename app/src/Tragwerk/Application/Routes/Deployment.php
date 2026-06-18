<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;
use Tragwerk\Application\Middleware\Conditional\RequiresActiveProject;
use Tragwerk\Application\Middleware\Redirect\ToActiveTeam;

final readonly class Deployment
{
    public function __construct(
        private MiddlewareFactory $middlewareFactory,
    ) {
    }

    public function registerRoutes(RouteCollectorInterface $routes): void
    {
        $routes->get(
            '/deployments',
            $this->middlewareFactory->prepare([
                new RequiresActiveProject($this->middlewareFactory->prepare([
                    Handler\Deployment\IndexHandler::class,
                ])),
                ToActiveTeam::class,
            ]),
            'deployment',
        );
        $routes->get(
            '/deployments/terminal',
            $this->middlewareFactory->prepare([
                new RequiresActiveProject($this->middlewareFactory->prepare([
                    Handler\Deployment\TerminalHandler::class,
                ])),
                ToActiveTeam::class,
            ]),
            'deployment.terminal',
        );
    }
}
