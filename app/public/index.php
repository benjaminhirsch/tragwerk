<?php

declare(strict_types=1);

use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

$app = (static function (): Application {
    $container = require 'config/container.php';
    assert($container instanceof ContainerInterface);
    $application = $container->get(Application::class);
    assert($application instanceof Application);
    $factory = $container->get(MiddlewareFactory::class);

    (require 'config/pipeline.php')($application, $factory, $container);
    (require 'config/routes.php')($application, $factory, $container);

    return $application;
})();

$handler = static function () use ($app): void {
    $response = $app->handle(ServerRequestFactory::fromGlobals());
    new SapiEmitter()->emit($response);
};

// Classic mode (e.g. PHP built-in server): handle a single request and exit.
if (! function_exists('frankenphp_handle_request')) {
    $handler();

    return;
}

// FrankenPHP worker mode: bootstrap stays warm, one loop iteration per request.
ignore_user_abort(true);

$maxRequests = (int) ($_SERVER['MAX_REQUESTS'] ?? 0);
for ($nbRequests = 0; $maxRequests === 0 || $nbRequests < $maxRequests; ++$nbRequests) {
    $keepRunning = frankenphp_handle_request($handler);
    gc_collect_cycles();

    if (! $keepRunning) {
        break;
    }
}
