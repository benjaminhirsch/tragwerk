<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Authorization\AuthorizationInterface;
use Mezzio\Helper\UrlHelper;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Domain\Enum\TeamPermission;

use function str_starts_with;

final readonly class TeamAuthorizationMiddleware implements MiddlewareInterface
{
    public const string OPTION_REQUIRE_PERMISSION = 'option-require-team-permission';

    public function __construct(
        private AuthorizationInterface $authorization,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeResult = $request->getAttribute(RouteResult::class);
        if (! $routeResult instanceof RouteResult) {
            return $handler->handle($request);
        }

        $matchedRoute = $routeResult->getMatchedRoute();
        if (! $matchedRoute instanceof Route) {
            return $handler->handle($request);
        }

        $permission = $matchedRoute->getOptions()[self::OPTION_REQUIRE_PERMISSION] ?? null;
        if (! $permission instanceof TeamPermission) {
            return $handler->handle($request);
        }

        if ($this->authorization->isGranted($permission->value, $request)) {
            return $handler->handle($request);
        }

        return $this->deny($request);
    }

    private function deny(ServerRequestInterface $request): ResponseInterface
    {
        // API routes return a parseable JSON error instead of an HTML redirect.
        if (str_starts_with($request->getUri()->getPath(), '/api/')) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $redirectTo = $this->urlHelper->generate('team');

        // HTMX requests follow 302 redirects silently and would inject the target
        // HTML into the swap target; signal a full-page redirect instead.
        if ($request->getHeaderLine('HX-Request') === 'true') {
            return new EmptyResponse(200, ['HX-Redirect' => $redirectTo]);
        }

        return new RedirectResponse($redirectTo);
    }
}
