<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Fig\Http\Message\RequestMethodInterface;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;

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
            $this->middlewareFactory->prepare([Handler\Project\IndexHandler::class]),
            'project',
        );

        $routes->route(
            '/projects/create',
            $this->middlewareFactory->prepare([Handler\Project\CreateHandler::class]),
            [RequestMethodInterface::METHOD_GET, RequestMethodInterface::METHOD_POST],
            'project.create',
        );

        $routes->get(
            '/projects/{id}',
            $this->middlewareFactory->prepare([Handler\Project\ShowHandler::class]),
            'project.show',
        );

        $routes->route(
            '/projects/{id}/edit',
            $this->middlewareFactory->prepare([Handler\Project\EditHandler::class]),
            [RequestMethodInterface::METHOD_GET, RequestMethodInterface::METHOD_POST],
            'project.edit',
        );

        $routes->post(
            '/projects/{id}/environments/redeploy',
            $this->middlewareFactory->prepare([Handler\Project\RedeployEnvironmentHandler::class]),
            'project.environment.redeploy',
        );

        $routes->post(
            '/projects/{id}/environments/sync-data',
            $this->middlewareFactory->prepare([Handler\Project\SyncEnvironmentDataHandler::class]),
            'project.environment.sync-data',
        );

        $routes->post(
            '/projects/{id}/delete',
            $this->middlewareFactory->prepare([Handler\Project\DeleteHandler::class]),
            'project.delete',
        );
    }
}
