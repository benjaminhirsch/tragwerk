<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Fig\Http\Message\RequestMethodInterface;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;

final readonly class Server
{
    public function __construct(
        private MiddlewareFactory $middlewareFactory,
    ) {
    }

    public function registerRoutes(RouteCollectorInterface $routes): void
    {
        $routes->get(
            '/servers',
            $this->middlewareFactory->prepare([
                Handler\Server\IndexHandler::class,
            ]),
            'server',
        );

        $routes
            ->route(
                '/servers/create',
                $this->middlewareFactory->prepare([Handler\Server\CreateHandler::class]),
                [
                    RequestMethodInterface::METHOD_GET,
                    RequestMethodInterface::METHOD_POST,
                ],
                'server.create',
            );

        $routes->post(
            '/servers/{id}/delete',
            $this->middlewareFactory->prepare([Handler\Server\DeleteHandler::class]),
            'server.delete',
        );

        $routes->route(
            '/servers/{id}/edit',
            $this->middlewareFactory->prepare([Handler\Server\EditHandler::class]),
            [
                RequestMethodInterface::METHOD_GET,
                RequestMethodInterface::METHOD_POST,
            ],
            'server.edit',
        );
    }
}
