<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;

final readonly class Queue
{
    public function __construct(
        private MiddlewareFactory $middlewareFactory,
    ) {
    }

    public function registerRoutes(RouteCollectorInterface $routes): void
    {
        $routes->get(
            '/queue',
            $this->middlewareFactory->prepare([Handler\Queue\IndexHandler::class]),
            'queue',
        );

        $routes->get(
            '/queue/{id}',
            $this->middlewareFactory->prepare([Handler\Queue\ShowHandler::class]),
            'queue.show',
        );

        $routes->get(
            '/queue/{id}/tabs/{tab}',
            $this->middlewareFactory->prepare([Handler\Queue\TabHandler::class]),
            'queue.show.tab',
        );

        $routes->post(
            '/queue/{id}/requeue',
            $this->middlewareFactory->prepare([Handler\Queue\RequeueHandler::class]),
            'queue.requeue',
        );

        $routes->post(
            '/queue/{id}/delete',
            $this->middlewareFactory->prepare([Handler\Queue\DeleteHandler::class]),
            'queue.delete',
        );
    }
}
