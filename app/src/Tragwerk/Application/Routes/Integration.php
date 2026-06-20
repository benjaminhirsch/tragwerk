<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Fig\Http\Message\RequestMethodInterface;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;
use Tragwerk\Application\Middleware\Conditional\RequiresActiveProject;
use Tragwerk\Application\Middleware\Redirect\ToActiveTeam;

final readonly class Integration
{
    public function __construct(
        private MiddlewareFactory $middlewareFactory,
    ) {
    }

    public function registerRoutes(RouteCollectorInterface $routes): void
    {
        $routes->get(
            '/integrations',
            $this->middlewareFactory->prepare([
                new RequiresActiveProject($this->middlewareFactory->prepare([
                    Handler\Integration\IndexHandler::class,
                ])),
                ToActiveTeam::class,
            ]),
            'integration',
        );

        $routes
            ->route(
                '/integrations/create',
                $this->middlewareFactory->prepare([Handler\Integration\CreateHandler::class]),
                [
                    RequestMethodInterface::METHOD_GET,
                    RequestMethodInterface::METHOD_POST,
                ],
                'integration.create',
            );

        $routes->post(
            '/integrations/{id}/delete',
            $this->middlewareFactory->prepare([Handler\Integration\DeleteHandler::class]),
            'integration.delete',
        );
    }
}
