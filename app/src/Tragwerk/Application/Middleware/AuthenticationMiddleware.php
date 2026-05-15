<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware;

use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Authentication\AuthenticationInterface;
use Mezzio\Authentication\UserInterface;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function assert;
use function is_bool;
use function str_starts_with;

/** @final */
final readonly class AuthenticationMiddleware implements MiddlewareInterface
{
    public const string OPTION_REQUIRE_AUTHENTICATION = 'option-require-authentication';

    public function __construct(
        protected AuthenticationInterface $auth,
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

        $isAuthenticationRequired = $matchedRoute->getOptions()[self::OPTION_REQUIRE_AUTHENTICATION] ?? true;
        assert(is_bool($isAuthenticationRequired));

        if (! $isAuthenticationRequired) {
            $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
            assert($session instanceof SessionInterface);
            if ($session->has(UserInterface::class)) {
                return $handler->handle($request->withAttribute(
                    UserInterface::class,
                    $this->auth->authenticate($request),
                ));
            }

            return $handler->handle($request);
        }

        $user = $this->auth->authenticate($request);
        if ($user !== null) {
            return $handler->handle($request->withAttribute(UserInterface::class, $user));
        }

        // API routes must not redirect to the login page — return JSON 401 so
        // fetch()-based clients receive a parseable error instead of HTML.
        if (str_starts_with($request->getUri()->getPath(), '/api/')) {
            return new JsonResponse(['error' => 'Unauthenticated'], 401);
        }

        return $this->auth->unauthorizedResponse($request);
    }
}
