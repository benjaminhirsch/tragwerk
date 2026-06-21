<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Fig\Http\Message\RequestMethodInterface;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;

final readonly class Credential
{
    public function __construct(
        private MiddlewareFactory $middlewareFactory,
    ) {
    }

    public function registerRoutes(RouteCollectorInterface $routes): void
    {
        $routes->get(
            '/credentials',
            $this->middlewareFactory->prepare([
                Handler\Credential\IndexHandler::class,
            ]),
            'credential',
        );

        $routes->route(
            '/credentials/create',
            $this->middlewareFactory->prepare([Handler\Credential\CreateHandler::class]),
            [
                RequestMethodInterface::METHOD_GET,
                RequestMethodInterface::METHOD_POST,
            ],
            'credential.create',
        );

        $routes->post(
            '/credentials/{id}/delete',
            $this->middlewareFactory->prepare([Handler\Credential\DeleteHandler::class]),
            'credential.delete',
        );

        $routes->route(
            '/credentials/{id}/edit',
            $this->middlewareFactory->prepare([Handler\Credential\EditHandler::class]),
            [
                RequestMethodInterface::METHOD_GET,
                RequestMethodInterface::METHOD_POST,
            ],
            'credential.edit',
        );
    }
}
