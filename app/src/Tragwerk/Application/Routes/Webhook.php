<?php

declare(strict_types=1);

namespace Tragwerk\Application\Routes;

use Fig\Http\Message\RequestMethodInterface;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\RouteCollectorInterface;
use Tragwerk\Application\Handler;
use Tragwerk\Application\Middleware\AuthenticationMiddleware;

final readonly class Webhook
{
    public function __construct(
        private MiddlewareFactory $middlewareFactory,
    ) {
    }

    public function registerRoutes(RouteCollectorInterface $routes): void
    {
        $routes->route(
            '/webhooks/git-push',
            $this->middlewareFactory->prepare([Handler\Webhook\GitPushHandler::class]),
            [RequestMethodInterface::METHOD_POST],
            'webhook.git-push',
        )->setOptions([AuthenticationMiddleware::OPTION_REQUIRE_AUTHENTICATION => false]);

        $routes->route(
            '/webhooks/{forge}/{projectId}',
            $this->middlewareFactory->prepare([Handler\Webhook\ForgeWebhookHandler::class]),
            [RequestMethodInterface::METHOD_POST],
            'webhook.forge',
        )->setOptions([AuthenticationMiddleware::OPTION_REQUIRE_AUTHENTICATION => false]);
    }
}
