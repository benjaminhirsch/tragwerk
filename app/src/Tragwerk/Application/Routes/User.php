<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Fig\Http\Message\RequestMethodInterface;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;
use Tragwerk\Application\Middleware;

final readonly class User
{
    public function __construct(
        private MiddlewareFactory $middlewareFactory,
    ) {
    }

    public function registerRoutes(RouteCollectorInterface $routes): void
    {
        $routes->route(
            '/account',
            $this->middlewareFactory->prepare([Handler\User\AccountHandler::class]),
            [RequestMethodInterface::METHOD_GET],
            'account',
        );

        $routes->post(
            '/account/profile',
            $this->middlewareFactory->prepare([Handler\User\UpdateProfileHandler::class]),
            'account.profile',
        );

        $routes->post(
            '/account/password',
            $this->middlewareFactory->prepare([Handler\User\ChangePasswordHandler::class]),
            'account.password',
        );

        $routes->post(
            '/account/ssh-keys',
            $this->middlewareFactory->prepare([Handler\User\AddSshKeyHandler::class]),
            'account.ssh-keys.add',
        );

        $routes->post(
            '/account/ssh-keys/delete',
            $this->middlewareFactory->prepare([Handler\User\DeleteSshKeyHandler::class]),
            'account.ssh-keys.delete',
        );

        $routes->route(
            '/account/2fa',
            $this->middlewareFactory->prepare([Handler\User\TwoFactorSetupHandler::class]),
            [RequestMethodInterface::METHOD_GET],
            '2fa.setup',
        );

        $routes->post(
            '/account/2fa/enable',
            $this->middlewareFactory->prepare([Handler\User\TwoFactorEnableHandler::class]),
            '2fa.enable',
        );

        $routes->post(
            '/account/2fa/disable',
            $this->middlewareFactory->prepare([Handler\User\TwoFactorDisableHandler::class]),
            '2fa.disable',
        );

        $routes->route(
            '/account/2fa/recovery-codes',
            $this->middlewareFactory->prepare([Handler\User\RecoveryCodesHandler::class]),
            [RequestMethodInterface::METHOD_GET, RequestMethodInterface::METHOD_POST],
            '2fa.recovery-codes',
        );

        // The login second-factor challenge: the user is only half-authenticated
        // here (no UserInterface in the session yet), so full auth is NOT required.
        $routes
            ->route(
                '/login/2fa',
                $this->middlewareFactory->prepare([Handler\User\TwoFactorChallengeHandler::class]),
                [RequestMethodInterface::METHOD_GET, RequestMethodInterface::METHOD_POST],
                '2fa.challenge',
            )
            ->setOptions([Middleware\AuthenticationMiddleware::OPTION_REQUIRE_AUTHENTICATION => false]);
    }
}
