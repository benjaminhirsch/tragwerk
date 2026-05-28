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
            '/projects/{id}/tabs/{tab}',
            $this->middlewareFactory->prepare([Handler\Project\TabHandler::class]),
            'project.show.tab',
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

        $routes->get(
            '/projects/{id}/environments',
            $this->middlewareFactory->prepare([Handler\Project\EnvironmentHandler::class]),
            'project.environment',
        );

        $routes->post(
            '/projects/{id}/environments/toggle',
            $this->middlewareFactory->prepare([Handler\Project\ToggleBranchHandler::class]),
            'project.environment.toggle',
        );

        $routes->post(
            '/projects/{id}/delete',
            $this->middlewareFactory->prepare([Handler\Project\DeleteHandler::class]),
            'project.delete',
        );
    }
}
