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

        $routes
            ->route(
                '/password-reset',
                $this->middlewareFactory->prepare([Handler\PasswordResetRequestHandler::class]),
                [
                    RequestMethodInterface::METHOD_GET,
                    RequestMethodInterface::METHOD_POST,
                ],
                'passwordReset.request',
            )
            ->setOptions([Middleware\AuthenticationMiddleware::OPTION_REQUIRE_AUTHENTICATION => false]);

        $routes
            ->route(
                '/password-reset/{token}',
                $this->middlewareFactory->prepare([Handler\PasswordResetApplyHandler::class]),
                [
                    RequestMethodInterface::METHOD_GET,
                    RequestMethodInterface::METHOD_POST,
                ],
                'passwordReset.apply',
            )
            ->setOptions([Middleware\AuthenticationMiddleware::OPTION_REQUIRE_AUTHENTICATION => false]);

        $routes
            ->get(
                '/confirm-email/{token}',
                $this->middlewareFactory->prepare([Handler\ConfirmEmailHandler::class]),
                'email.confirm',
            )
            ->setOptions([Middleware\AuthenticationMiddleware::OPTION_REQUIRE_AUTHENTICATION => false]);

        $routes->get(
            '/',
            $this->middlewareFactory->prepare([
                new Middleware\Conditional\Authenticated($this->middlewareFactory->prepare([
                    Middleware\Redirect\ToDefaultTeam::class,
                ])),
                Handler\HomeHandler::class,
            ]),
            'home',
        )->setOptions([Middleware\AuthenticationMiddleware::OPTION_REQUIRE_AUTHENTICATION => false]);

        $routes
            ->get('/schema.xsd', $this->middlewareFactory->prepare([Handler\SchemaHandler::class]), 'schema')
            ->setOptions([Middleware\AuthenticationMiddleware::OPTION_REQUIRE_AUTHENTICATION => false]);

        $routes
            ->get('/imprint', $this->middlewareFactory->prepare([Handler\Legal\ImprintHandler::class]), 'imprint')
            ->setOptions([Middleware\AuthenticationMiddleware::OPTION_REQUIRE_AUTHENTICATION => false]);

        $routes
            ->get(
                '/privacy-policy',
                $this->middlewareFactory->prepare([Handler\Legal\PrivacyPolicyHandler::class]),
                'privacy-policy',
            )
            ->setOptions([Middleware\AuthenticationMiddleware::OPTION_REQUIRE_AUTHENTICATION => false]);

        $routes
            ->get(
                '/terms-and-conditions',
                $this->middlewareFactory->prepare([Handler\Legal\TermsHandler::class]),
                'terms-and-conditions',
            )
            ->setOptions([Middleware\AuthenticationMiddleware::OPTION_REQUIRE_AUTHENTICATION => false]);
    }
}
