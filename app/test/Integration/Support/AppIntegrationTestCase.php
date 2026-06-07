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
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tragwerk\Application\Middleware\Csrf\RequireValidCsrfToken;
use Tragwerk\Infrastructure\Git\BareRepository;
use Tragwerk\Infrastructure\Queue\Producer as InfraProducer;

use function assert;
use function is_callable;
use function is_dir;
use function mkdir;
use function preg_match;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

abstract class AppIntegrationTestCase extends IntegrationTestCase
{
    protected Application $app;
    private string $tempRepoDir;

    protected function setUp(): void
    {
        parent::setUp(); // builds container, gets connection, starts transaction

        $this->tempRepoDir = sys_get_temp_dir() . '/tragwerk-test-repos-' . uniqid();
        @mkdir($this->tempRepoDir, 0755, true);

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

        // Isolate git repositories in a per-test temp directory so they are cleaned up after
        $this->container->setService(BareRepository::class, new BareRepository($this->tempRepoDir, 'http://app'));

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

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempRepoDir);

        parent::tearDown();
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            assert($file instanceof SplFileInfo);
            $realPath = $file->getRealPath();
            assert($realPath !== false);
            if ($file->isDir()) {
                rmdir($realPath);
            } else {
                unlink($realPath);
            }
        }

        rmdir($path);
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     */
    protected function dispatch(
        string $method,
        string $path,
        array $body = [],
        string $cookie = '',
        array $headers = [],
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

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
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
