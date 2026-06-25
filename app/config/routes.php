<?php

declare(strict_types=1);

use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Psr\Container\ContainerInterface;
use Tragwerk\Application\Routes;

return static function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {
    $routeCollector = $container->get(RouteCollectorInterface::class);
    assert($routeCollector instanceof RouteCollectorInterface);

    $middlewareFactory = $container->get(MiddlewareFactory::class);
    assert($middlewareFactory instanceof MiddlewareFactory);

    new Routes\App($middlewareFactory)->registerRoutes($routeCollector);
    new Routes\Project($middlewareFactory)->registerRoutes($routeCollector);
    new Routes\Credential($middlewareFactory)->registerRoutes($routeCollector);
    new Routes\Registry($middlewareFactory)->registerRoutes($routeCollector);
    new Routes\Queue($middlewareFactory)->registerRoutes($routeCollector);
    new Routes\Server($middlewareFactory)->registerRoutes($routeCollector);
    new Routes\Team($middlewareFactory)->registerRoutes($routeCollector);
    new Routes\Profile($middlewareFactory)->registerRoutes($routeCollector);
    new Routes\User($middlewareFactory)->registerRoutes($routeCollector);
    new Routes\Webhook($middlewareFactory)->registerRoutes($routeCollector);
    new Routes\Environment($middlewareFactory)->registerRoutes($routeCollector);
    new Routes\Container($middlewareFactory)->registerRoutes($routeCollector);
    new Routes\Deployment($middlewareFactory)->registerRoutes($routeCollector);
    new Routes\Variables($middlewareFactory)->registerRoutes($routeCollector);
    new Routes\Configuration($middlewareFactory)->registerRoutes($routeCollector);
    new Routes\Integration($middlewareFactory)->registerRoutes($routeCollector);
    new Routes\Metrics($middlewareFactory)->registerRoutes($routeCollector);
    new Routes\Log($middlewareFactory)->registerRoutes($routeCollector);
    new Routes\Domain($middlewareFactory)->registerRoutes($routeCollector);
};
