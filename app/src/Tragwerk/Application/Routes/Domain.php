<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Fig\Http\Message\RequestMethodInterface;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;
use Tragwerk\Application\Middleware\Conditional\RequiresActiveEnvironment;
use Tragwerk\Application\Middleware\Redirect\ToActiveProject;

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
                new RequiresActiveEnvironment($this->middlewareFactory->prepare([
                    Handler\Domain\IndexHandler::class,
                ])),
                ToActiveProject::class,
            ]),
            'domain',
        );

        $routes->route(
            '/domains/create',
            $this->middlewareFactory->prepare([
                new RequiresActiveEnvironment($this->middlewareFactory->prepare([
                    Handler\Domain\CreateHandler::class,
                ])),
                ToActiveProject::class,
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
                new RequiresActiveEnvironment($this->middlewareFactory->prepare([
                    Handler\Domain\DeleteHandler::class,
                ])),
                ToActiveProject::class,
            ]),
            'domain.delete',
        );

        $routes->post(
            '/domains/{domainId}/primary',
            $this->middlewareFactory->prepare([
                new RequiresActiveEnvironment($this->middlewareFactory->prepare([
                    Handler\Domain\SetPrimaryHandler::class,
                ])),
                ToActiveProject::class,
            ]),
            'domain.primary',
        );
    }
}
