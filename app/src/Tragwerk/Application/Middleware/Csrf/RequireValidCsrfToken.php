<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware\Csrf;

use Fig\Http\Message\RequestMethodInterface;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\Csrf;

use function assert;
use function is_array;
use function is_bool;
use function is_string;

final readonly class RequireValidCsrfToken implements MiddlewareInterface
{
    public const string OPTION_DISABLE_CSRF_CHECK = 'option-disable-csrf-check';

    public function __construct(
        private ResponseRenderer $responseRenderer,
        private Csrf $csrfService,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeResult = $request->getAttribute(RouteResult::class);
        if (! ($routeResult instanceof RouteResult)) {
            return $handler->handle($request);
        }

        $matchedRoute = $routeResult->getMatchedRoute();
        if (! ($matchedRoute instanceof Route)) {
            return $handler->handle($request);
        }

        $disableCsrfCheck = $matchedRoute->getOptions()[self::OPTION_DISABLE_CSRF_CHECK] ?? null;
        assert($disableCsrfCheck === null || is_bool($disableCsrfCheck));

        if ($disableCsrfCheck === true) {
            return $handler->handle($request);
        }

        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        assert($session instanceof SessionInterface);

        $token = null;
        // CSRF Tokens should only be transmitted via POST body; not via query parameters
        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $body = $request->getParsedBody();
            assert(is_array($body));
            $token = $body['csrf_token'] ?? null;
        }

        $isValidToken = is_string($token) && $this->csrfService->isValidToken($session, $token);
        if ($isValidToken) {
            return $handler->handle($request);
        }

        return $this->responseRenderer->render(
            $request,
            'error::400',
            ['description' => 'Invalid CSRF Token'],
            400,
        );
    }
}
