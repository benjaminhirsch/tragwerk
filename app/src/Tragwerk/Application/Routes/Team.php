<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Fig\Http\Message\RequestMethodInterface;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;
use Tragwerk\Application\Middleware;
use Tragwerk\Domain\Enum\TeamPermission;

final readonly class Team
{
    public function __construct(
        private MiddlewareFactory $middlewareFactory,
    ) {
    }

    public function registerRoutes(RouteCollectorInterface $routes): void
    {
        $routes->get(
            '/teams',
            $this->middlewareFactory->prepare([
                Handler\Team\IndexHandler::class,
            ]),
            'team',
        );

        $routes
            ->route(
                '/teams/create',
                $this->middlewareFactory->prepare([Handler\Team\CreateHandler::class]),
                [
                    RequestMethodInterface::METHOD_GET,
                    RequestMethodInterface::METHOD_POST,
                ],
                'team.create',
            );

        $routes->post(
            '/teams/{id}/delete',
            $this->middlewareFactory->prepare([Handler\Team\DeleteHandler::class]),
            'team.delete',
        )->setOptions([
            Middleware\TeamAuthorizationMiddleware::OPTION_REQUIRE_PERMISSION => TeamPermission::DeleteTeam,
        ]);

        $routes
            ->route(
                '/teams/{id}/edit',
                $this->middlewareFactory->prepare([Handler\Team\EditHandler::class]),
                [
                    RequestMethodInterface::METHOD_GET,
                    RequestMethodInterface::METHOD_POST,
                ],
                'team.edit',
            )->setOptions([
                Middleware\TeamAuthorizationMiddleware::OPTION_REQUIRE_PERMISSION => TeamPermission::EditTeam,
            ]);

        $routes->post(
            '/teams/{id}/members/remove',
            $this->middlewareFactory->prepare([Handler\Team\RemoveMemberHandler::class]),
            'team.members.remove',
        )->setOptions([
            Middleware\TeamAuthorizationMiddleware::OPTION_REQUIRE_PERMISSION => TeamPermission::ManageMembers,
        ]);

        $routes->post(
            '/teams/{id}/members/role',
            $this->middlewareFactory->prepare([Handler\Team\RoleChangeHandler::class]),
            'team.members.role',
        )->setOptions([
            Middleware\TeamAuthorizationMiddleware::OPTION_REQUIRE_PERMISSION => TeamPermission::ManageMembers,
        ]);

        $routes->post(
            '/teams/{id}/members/invite',
            $this->middlewareFactory->prepare([Handler\Team\InviteMembersHandler::class]),
            'team.members.invite',
        )->setOptions([
            Middleware\TeamAuthorizationMiddleware::OPTION_REQUIRE_PERMISSION => TeamPermission::ManageMembers,
        ]);

        $routes->post(
            '/teams/switch',
            $this->middlewareFactory->prepare([Handler\Team\SwitchHandler::class]),
            'team.switch',
        );

        $routes->route(
            '/teams/emails',
            $this->middlewareFactory->prepare([Handler\Team\EmailFieldsHandler::class]),
            [RequestMethodInterface::METHOD_POST],
            'team.emails',
        );

        $routes
            ->route(
                '/teams/invite/{token}',
                $this->middlewareFactory->prepare([Handler\Team\InviteRegisterHandler::class]),
                [
                    RequestMethodInterface::METHOD_GET,
                    RequestMethodInterface::METHOD_POST,
                ],
                'team.invite',
            )
            ->setOptions([Middleware\AuthenticationMiddleware::OPTION_REQUIRE_AUTHENTICATION => false]);

        $routes->get(
            '/teams/{id}/tabs/{tab}',
            $this->middlewareFactory->prepare([Handler\Team\TabHandler::class]),
            'team.show.tab',
        )->setOptions([
            Middleware\TeamAuthorizationMiddleware::OPTION_REQUIRE_PERMISSION => TeamPermission::ViewTeam,
        ]);

        $routes->get(
            '/teams/{id}',
            $this->middlewareFactory->prepare([Handler\Team\OverviewHandler::class]),
            'team.show',
        )->setOptions([
            Middleware\TeamAuthorizationMiddleware::OPTION_REQUIRE_PERMISSION => TeamPermission::ViewTeam,
        ]);
    }
}
