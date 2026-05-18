<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Support;

use Mezzio\Application;
use Mezzio\Container\ErrorResponseGeneratorFactory;
use Mezzio\Helper\UrlHelper;
use Mezzio\Middleware\ErrorResponseGenerator;
use Mezzio\MiddlewareFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tragwerk\Application\Middleware\Csrf\RequireValidCsrfToken;
use Tragwerk\Infrastructure\Queue\Producer as InfraProducer;

use function assert;
use function is_callable;
use function preg_match;

abstract class AppIntegrationTestCase extends IntegrationTestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp(); // builds container, gets connection, starts transaction

        // Override services that require external infrastructure
        $this->container->setAllowOverride(true);

        // Sessions in-memory — no sessions table needed in app_test
        $this->container->setService('session-cache', new ArrayAdapter());

        // Bypass CSRF token validation — we test business logic, not security mechanisms
        $this->container->setFactory(
            RequireValidCsrfToken::class,
            static fn (): NullMiddleware => new NullMiddleware(),
        );

        // Suppress queue writes — SendRegistrationMail still runs but becomes a no-op
        $this->container->setService(InfraProducer::class, new NullProducer());

        // Replace Whoops error generator with the standard one: Whoops::register() sets
        // global error/exception handlers that PHPUnit detects as leaked handler state.
        /** @phpstan-ignore argument.type */
        $this->container->setFactory(ErrorResponseGenerator::class, ErrorResponseGeneratorFactory::class);

        $this->container->setAllowOverride(false);

        $appDir = __DIR__ . '/../../../';
        $app    = $this->container->get(Application::class);
        assert($app instanceof Application);
        $this->app         = $app;
        $middlewareFactory = $this->container->get(MiddlewareFactory::class);
        assert($middlewareFactory instanceof MiddlewareFactory);

        $pipeline = require $appDir . 'config/pipeline.php';
        assert(is_callable($pipeline));
        $pipeline($this->app, $middlewareFactory, $this->container);

        $routes = require $appDir . 'config/routes.php';
        assert(is_callable($routes));
        $routes($this->app, $middlewareFactory, $this->container);
    }

    /** @param array<string, string> $body */
    protected function dispatch(
        string $method,
        string $path,
        array $body = [],
        string $cookie = '',
    ): ResponseInterface {
        $factory = new Psr17Factory();
        $request = $factory->createServerRequest($method, $path);

        if ($body !== []) {
            $request = $request
                ->withParsedBody($body)
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        }

        if ($cookie !== '') {
            $request = $request->withHeader('Cookie', $cookie);
        }

        return $this->app->handle($request);
    }

    /** @param array<string, string> $params */
    protected function url(string $routeName, array $params = []): string
    {
        $helper = $this->container->get(UrlHelper::class);
        assert($helper instanceof UrlHelper);

        return $helper->generate($routeName !== '' ? $routeName : null, $params);
    }

    protected function getSessionCookie(ResponseInterface $response): string
    {
        preg_match('/tragwerk-session=[^;]+/', $response->getHeaderLine('Set-Cookie'), $m);

        return $m[0] ?? '';
    }
}
