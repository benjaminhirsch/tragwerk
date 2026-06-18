<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Fig\Http\Message\RequestMethodInterface;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;
use Tragwerk\Application\Middleware\Conditional\RequiresActiveProject;
use Tragwerk\Application\Middleware\Redirect\ToActiveTeam;

final readonly class Variables
{
    public function __construct(
        private MiddlewareFactory $middlewareFactory,
    ) {
    }

    public function registerRoutes(RouteCollectorInterface $routes): void
    {
        $routes->get(
            '/variables',
            $this->middlewareFactory->prepare([
                new RequiresActiveProject($this->middlewareFactory->prepare([
                    Handler\Variables\IndexHandler::class,
                ])),
                ToActiveTeam::class,
            ]),
            'variable',
        );

        $routes
            ->route(
                '/variables/create',
                $this->middlewareFactory->prepare([Handler\Variables\CreateHandler::class]),
                [
                    RequestMethodInterface::METHOD_GET,
                    RequestMethodInterface::METHOD_POST,
                ],
                'variable.create',
            );

        $routes->post(
            '/variables/{id}/delete',
            $this->middlewareFactory->prepare([Handler\Variables\DeleteHandler::class]),
            'variable.delete',
        );

        $routes->route(
            '/variables/{id}/edit',
            $this->middlewareFactory->prepare([Handler\Variables\EditHandler::class]),
            [
                RequestMethodInterface::METHOD_GET,
                RequestMethodInterface::METHOD_POST,
            ],
            'variable.edit',
        );
    }
}
