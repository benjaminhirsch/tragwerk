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
            '/projects/{id}/environments/redeploy',
            $this->middlewareFactory->prepare([Handler\Project\RedeployEnvironmentHandler::class]),
            'project.environment.redeploy',
        );

        $routes->post(
            '/projects/{id}/environments/sync-data',
            $this->middlewareFactory->prepare([Handler\Project\SyncEnvironmentDataHandler::class]),
            'project.environment.sync-data',
        );

        $routes->get(
            '/projects/{id}/environments/branch-list',
            $this->middlewareFactory->prepare([Handler\Project\BranchListHandler::class]),
            'project.environment.branch-list',
        );

        $routes->get(
            '/projects/{id}/environments/deploy-log',
            $this->middlewareFactory->prepare([Handler\Project\DeployLogHandler::class]),
            'project.environment.deploy-log',
        );

        $routes->get(
            '/projects/{id}/environments/deploy-jobs/{jobId}/output',
            $this->middlewareFactory->prepare([Handler\Project\DeployJobOutputHandler::class]),
            'project.environment.deploy-job-output',
        );

        $routes->get(
            '/projects/{id}/environments/container-status',
            $this->middlewareFactory->prepare([Handler\Project\ContainerStatusHandler::class]),
            'project.environment.container-status',
        );

        $routes->get(
            '/projects/{id}/environments/logs',
            $this->middlewareFactory->prepare([Handler\Project\EnvironmentLogsHandler::class]),
            'project.environment.logs',
        );

        $routes->get(
            '/projects/{id}/environments/metrics-live',
            $this->middlewareFactory->prepare([Handler\Project\EnvironmentMetricsLiveHandler::class]),
            'project.environment.metrics-live',
        );

        $routes->get(
            '/projects/{id}/environments/metrics-data',
            $this->middlewareFactory->prepare([Handler\Project\EnvironmentMetricsDataHandler::class]),
            'project.environment.metrics-data',
        );

        $routes->get(
            '/projects/{id}/environments/volume-sizes',
            $this->middlewareFactory->prepare([Handler\Project\VolumeSizesHandler::class]),
            'project.environment.volume-sizes',
        );

        $routes->get(
            '/projects/{id}/environments/download',
            $this->middlewareFactory->prepare([Handler\Project\DownloadBuildHandler::class]),
            'project.environment.download',
        );

        $routes->post(
            '/projects/{id}/environments/{branch}/domains',
            $this->middlewareFactory->prepare([Handler\Project\Domain\AddDomainHandler::class]),
            'project.domain.add',
        );

        $routes->post(
            '/projects/{id}/environments/{branch}/domains/{domainId}/delete',
            $this->middlewareFactory->prepare([Handler\Project\Domain\DeleteDomainHandler::class]),
            'project.domain.delete',
        );

        $routes->post(
            '/projects/{id}/environments/{branch}/domains/{domainId}/primary',
            $this->middlewareFactory->prepare([Handler\Project\Domain\SetPrimaryDomainHandler::class]),
            'project.domain.primary',
        );

        $routes->post(
            '/projects/{id}/delete',
            $this->middlewareFactory->prepare([Handler\Project\DeleteHandler::class]),
            'project.delete',
        );
    }
}
