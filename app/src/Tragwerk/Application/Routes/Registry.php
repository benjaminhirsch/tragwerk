<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Fig\Http\Message\RequestMethodInterface;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;

final readonly class Registry
{
    public function __construct(
        private MiddlewareFactory $middlewareFactory,
    ) {
    }

    public function registerRoutes(RouteCollectorInterface $routes): void
    {
        $routes->get(
            '/registries',
            $this->middlewareFactory->prepare([Handler\Registry\IndexHandler::class]),
            'registry',
        );

        $routes->route(
            '/registries/create',
            $this->middlewareFactory->prepare([Handler\Registry\CreateHandler::class]),
            [RequestMethodInterface::METHOD_GET, RequestMethodInterface::METHOD_POST],
            'registry.create',
        );

        $routes->post(
            '/registries/{id}/delete',
            $this->middlewareFactory->prepare([Handler\Registry\DeleteHandler::class]),
            'registry.delete',
        );

        $routes->route(
            '/registries/{id}/edit',
            $this->middlewareFactory->prepare([Handler\Registry\EditHandler::class]),
            [RequestMethodInterface::METHOD_GET, RequestMethodInterface::METHOD_POST],
            'registry.edit',
        );
    }
}
