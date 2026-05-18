<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Fig\Http\Message\RequestMethodInterface;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;
use Tragwerk\Application\Middleware;

final readonly class Project
{
    public function __construct(
        private MiddlewareFactory $middlewareFactory,
    ) {
    }

    public function registerRoutes(RouteCollectorInterface $routes): void
    {
        $routes->get(
            '/projects',
            $this->middlewareFactory->prepare([
                Handler\Project\IndexHandler::class,
            ]),
            'project',
        );

        $routes
            ->route(
                '/projects/create',
                $this->middlewareFactory->prepare([Handler\Project\CreateHandler::class]),
                [
                    RequestMethodInterface::METHOD_GET,
                    RequestMethodInterface::METHOD_POST,
                ],
                'project.create',
            );

        $routes->post(
            '/projects/{id}/delete',
            $this->middlewareFactory->prepare([Handler\Project\DeleteHandler::class]),
            'project.delete',
        );

        $routes
            ->route(
                '/projects/{id}/edit',
                $this->middlewareFactory->prepare([Handler\Project\EditHandler::class]),
                [
                    RequestMethodInterface::METHOD_GET,
                    RequestMethodInterface::METHOD_POST,
                ],
                'project.edit',
            );

        $routes->post(
            '/projects/{id}/members/remove',
            $this->middlewareFactory->prepare([Handler\Project\RemoveMemberHandler::class]),
            'project.members.remove',
        );

        $routes->post(
            '/projects/switch',
            $this->middlewareFactory->prepare([Handler\Project\SwitchHandler::class]),
            'project.switch',
        );

        $routes->route(
            '/projects/emails',
            $this->middlewareFactory->prepare([Handler\Project\EmailFieldsHandler::class]),
            [RequestMethodInterface::METHOD_POST],
            'project.emails',
        );

        $routes
            ->route(
                '/projects/invite/{token}',
                $this->middlewareFactory->prepare([Handler\Project\InviteRegisterHandler::class]),
                [
                    RequestMethodInterface::METHOD_GET,
                    RequestMethodInterface::METHOD_POST,
                ],
                'project.invite',
            )
            ->setOptions([Middleware\AuthenticationMiddleware::OPTION_REQUIRE_AUTHENTICATION => false]);
    }
}
