<?php

declare(strict_types=1);

use Laminas\Stratigility\Middleware\ErrorHandler;
use Mezzio\Application;
use Mezzio\Handler\NotFoundHandler;
use Mezzio\Helper\UrlHelperMiddleware;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\ImplicitHeadMiddleware;
use Mezzio\Router\Middleware\ImplicitOptionsMiddleware;
use Mezzio\Router\Middleware\MethodNotAllowedMiddleware;
use Mezzio\Router\Middleware\RouteMiddleware;
use Mezzio\Session\SessionMiddleware;
use Psr\Container\ContainerInterface;
use Tragwerk\Application\Middleware;
use Tragwerk\Application\Template;

/**
 * Setup middleware pipeline:
 */

return static function (
    Application $app,
    MiddlewareFactory $factory,
    ContainerInterface $container,
): void {
    $config = $container->get('config');
    assert(is_array($config));

    // The error handler should be the first (most outer) middleware to catch
    // all Exceptions.
    $app->pipe(ErrorHandler::class);

    // Debugging
    $app->pipe(Middleware\ParseRawJsonBody::class);
    $app->pipe(Middleware\SetServerUrl::class);

    // Security headers (CSP nonce must be generated before templates render).
    $app->pipe(Template\Extension\Csp::class);

    // Pipe more middleware here that you want to execute on every request:
    // - bootstrapping
    // - pre-conditions
    // - modifications to outgoing responses
    //
    // Piped Middleware may be either callables or service names. Middleware may
    // also be passed as an array; each item in the array must resolve to
    // middleware eventually (i.e., callable or service name).
    //
    // Middleware can be attached to specific paths, allowing you to mix and match
    // applications under a common domain.  The handlers in each middleware
    // attached this way will see a URI with the matched path segment removed.
    //
    // i.e., path of "/api/member/profile" only passes "/member/profile" to $apiMiddleware
    // - $app->pipe('/api', $apiMiddleware);
    // - $app->pipe('/docs', $apiDocMiddleware);
    // - $app->pipe('/files', $filesMiddleware);

    // Register the routing middleware in the middleware pipeline.
    // This middleware registers the Mezzio\Router\RouteResult request attribute.
    $app->pipe(RouteMiddleware::class);

    // The following handle routing failures for common conditions:
    // - HEAD request but no routes answer that method
    // - OPTIONS request but no routes answer that method
    // - method not allowed
    // Order here matters; the MethodNotAllowedMiddleware should be placed
    // after the Implicit*Middleware.
    $app->pipe(ImplicitHeadMiddleware::class);
    $app->pipe(ImplicitOptionsMiddleware::class);
    $app->pipe(MethodNotAllowedMiddleware::class);

    // Seed the UrlHelper with the routing results:
    $app->pipe(UrlHelperMiddleware::class);

    // Add more middleware here that needs to introspect the routing results; this
    // might include:
    //
    // - route-based authentication
    // - route-based validation
    // - etc.
    // Debugging
    $app->pipe(SessionMiddleware::class);
    $app->pipe(Middleware\NegotiateLocale::class);
    $app->pipe(Middleware\TwoFactorPendingMiddleware::class);
    $app->pipe(Middleware\AuthenticationMiddleware::class);
    $app->pipe(Middleware\SetTranslatorLocale::class);
    $app->pipe(Template\Extension\Csrf::class);
    $app->pipe(Template\Extension\Authentication::class);
    $app->pipe(Template\Extension\Locale::class);
    $app->pipe(Middleware\TeamMiddleware::class);
    $app->pipe(Middleware\ProjectMiddleware::class);
    $app->pipe(Middleware\EnvironmentMiddleware::class);
    $app->pipe(Template\Extension\TeamContext::class);
    $app->pipe(Template\Extension\ActiveUriPath::class);
    $app->pipe(Template\Extension\ProjectContext::class);
    $app->pipe(Template\Extension\ProjectContext::class);
    $app->pipe(Template\Extension\EnvironmentContext::class);

    $app->pipe(new Middleware\Conditional\Method(
        ['POST'],
        $factory->prepare([
            new Middleware\Conditional\NoJson($factory->prepare([
                Middleware\Csrf\RequireValidCsrfToken::class,
                Middleware\Csrf\RemoveCsrfTokenFromRequest::class,
            ])),
        ]),
    ));

    // Register the dispatch middleware in the middleware pipeline
    $app->pipe(DispatchMiddleware::class);

    // At this point, if no Response is returned by any middleware, the
    // NotFoundHandler kicks in; alternately, you can provide other fallback
    // middleware to execute.
    $app->pipe(NotFoundHandler::class);
};
