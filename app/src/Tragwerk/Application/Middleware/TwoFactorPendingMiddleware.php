<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Mezzio\Router\RouteResult;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Service\TwoFactor\TwoFactorSession;

use function in_array;
use function str_starts_with;

/**
 * Guards the brief window between a correct password and a confirmed second
 * factor. While {@see TwoFactorSession} is pending, the user has no full
 * session, so this middleware diverts every protected route to the challenge
 * instead of letting AuthenticationMiddleware bounce them to /login.
 *
 * Must be piped AFTER SessionMiddleware/RouteMiddleware and BEFORE
 * AuthenticationMiddleware.
 */
final readonly class TwoFactorPendingMiddleware implements MiddlewareInterface
{
    /** Routes a pending user may still reach. */
    private const array ALLOWED_ROUTES = ['2fa.challenge', 'logout', 'login'];

    public function __construct(
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        if (! $session instanceof SessionInterface || ! TwoFactorSession::isPending($session)) {
            return $handler->handle($request);
        }

        // Abandon stale pending state and let normal handling resume.
        if (TwoFactorSession::isExpired($session)) {
            TwoFactorSession::clear($session);

            return $handler->handle($request);
        }

        $routeResult = $request->getAttribute(RouteResult::class);
        if ($routeResult instanceof RouteResult) {
            $routeName = $routeResult->getMatchedRouteName();
            if ($routeName !== false && in_array($routeName, self::ALLOWED_ROUTES, true)) {
                return $handler->handle($request);
            }
        }

        $location = $this->urlHelper->generate('2fa.challenge');

        if (str_starts_with($request->getUri()->getPath(), '/api/')) {
            return new JsonResponse(['error' => 'Two-factor authentication required'], 401);
        }

        if ($request->getHeaderLine('HX-Request') === 'true') {
            return new EmptyResponse(200, ['HX-Redirect' => $location]);
        }

        return new RedirectResponse($location);
    }
}
