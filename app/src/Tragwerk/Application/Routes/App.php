<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Fig\Http\Message\RequestMethodInterface;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;
use Tragwerk\Application\Middleware;

final readonly class App
{
    public function __construct(
        private MiddlewareFactory $middlewareFactory,
    ) {
    }

    public function registerRoutes(RouteCollectorInterface $routes): void
    {
        $routes
            ->route(
                '/register',
                $this->middlewareFactory->prepare([Handler\UserRegisterHandler::class]),
                [
                    RequestMethodInterface::METHOD_GET,
                    RequestMethodInterface::METHOD_POST,
                ],
                'register',
            )
            ->setOptions([Middleware\AuthenticationMiddleware::OPTION_REQUIRE_AUTHENTICATION => false]);

        $routes
            ->route(
                '/login',
                $this->middlewareFactory->prepare([Handler\LoginHandler::class]),
                [
                    RequestMethodInterface::METHOD_GET,
                    RequestMethodInterface::METHOD_POST,
                ],
                'login',
            )
            ->setOptions([Middleware\AuthenticationMiddleware::OPTION_REQUIRE_AUTHENTICATION => false]);
        $routes->post('/logout', $this->middlewareFactory->prepare([Middleware\Logout::class]), 'logout');

        $routes->get(
            '/',
            $this->middlewareFactory->prepare([
                Handler\HomeHandler::class,
            ]),
            'home',
        )->setOptions([Middleware\AuthenticationMiddleware::OPTION_REQUIRE_AUTHENTICATION => false]);
    }
}
