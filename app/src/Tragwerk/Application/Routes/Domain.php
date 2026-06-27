<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Fig\Http\Message\RequestMethodInterface;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;
use Tragwerk\Application\Middleware\Conditional\RequiresActiveProject;
use Tragwerk\Application\Middleware\Redirect\ToActiveTeam;

final readonly class Domain
{
    public function __construct(
        private MiddlewareFactory $middlewareFactory,
    ) {
    }

    public function registerRoutes(RouteCollectorInterface $routes): void
    {
        $routes->get(
            '/domains',
            $this->middlewareFactory->prepare([
                new RequiresActiveProject($this->middlewareFactory->prepare([
                    Handler\Domain\IndexHandler::class,
                ])),
                ToActiveTeam::class,
            ]),
            'domain',
        );

        $routes->route(
            '/domains/create',
            $this->middlewareFactory->prepare([
                new RequiresActiveProject($this->middlewareFactory->prepare([
                    Handler\Domain\CreateHandler::class,
                ])),
                ToActiveTeam::class,
            ]),
            [
                RequestMethodInterface::METHOD_GET,
                RequestMethodInterface::METHOD_POST,
            ],
            'domain.create',
        );

        $routes->post(
            '/domains/{domainId}/delete',
            $this->middlewareFactory->prepare([
                new RequiresActiveProject($this->middlewareFactory->prepare([
                    Handler\Domain\DeleteHandler::class,
                ])),
                ToActiveTeam::class,
            ]),
            'domain.delete',
        );

        $routes->post(
            '/domains/{domainId}/primary',
            $this->middlewareFactory->prepare([
                new RequiresActiveProject($this->middlewareFactory->prepare([
                    Handler\Domain\SetPrimaryHandler::class,
                ])),
                ToActiveTeam::class,
            ]),
            'domain.primary',
        );
    }
}
