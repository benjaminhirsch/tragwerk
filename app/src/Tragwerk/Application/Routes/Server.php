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

        $routes->post(
            '/servers/{id}/setup',
            $this->middlewareFactory->prepare([Handler\Server\SetupHandler::class]),
            'server.setup.start',
        );

        $routes->get(
            '/servers/{id}/setup/{jobId}',
            $this->middlewareFactory->prepare([Handler\Server\SetupPageHandler::class]),
            'server.setup',
        );

        $routes->get(
            '/servers/{id}/setup/{jobId}/log',
            $this->middlewareFactory->prepare([Handler\Server\SetupLogHandler::class]),
            'server.setup.log',
        );

        $routes->get(
            '/servers/{id}/tabs/{tab}',
            $this->middlewareFactory->prepare([Handler\Server\TabHandler::class]),
            'server.show.tab',
        );

        $routes->get(
            '/servers/{id}/metrics/live',
            $this->middlewareFactory->prepare([Handler\Server\MetricsLiveHandler::class]),
            'server.metrics.live',
        );

        $routes->get(
            '/servers/{id}/metrics/data',
            $this->middlewareFactory->prepare([Handler\Server\MetricsDataHandler::class]),
            'server.metrics.data',
        );

        $routes->get(
            '/servers/{id}',
            $this->middlewareFactory->prepare([Handler\Server\ShowHandler::class]),
            'server.show',
        );
    }
}
