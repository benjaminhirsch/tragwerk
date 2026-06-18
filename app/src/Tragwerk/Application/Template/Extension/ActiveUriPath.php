<?php

declare(strict_types=1);

namespace Tragwerk\Application\Template\Extension;

use League\Plates\Engine;
use League\Plates\Extension\ExtensionInterface;
use Mezzio\Router\RouteResult;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function assert;
use function in_array;

final class ActiveUriPath implements MiddlewareInterface, ExtensionInterface
{
    private ServerRequestInterface|null $request = null;

    #[Override]
    public function register(Engine $engine): void
    {
        $engine->registerFunction('routeDoesMatch', [$this, 'routeDoesMatch']);
    }

    public function routeDoesMatch(string ...$routeName): bool
    {
        $routeResult = $this->request?->getAttribute(RouteResult::class) ?? null;
        if ($routeResult === null) {
            return false;
        }

        assert($routeResult instanceof RouteResult);

        return in_array(
            $routeResult->getMatchedRouteName(),
            $routeName,
            true,
        );
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->request = $request;

        try {
            $response = $handler->handle($request);
        } finally {
            $this->request = null;
        }

        return $response;
    }
}
