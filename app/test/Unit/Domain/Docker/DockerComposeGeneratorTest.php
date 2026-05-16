<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\Docker;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Docker\DockerComposeGenerator;
use Tragwerk\Domain\Docker\ServiceImageResolver;
use Tragwerk\Domain\Enum\ApplicationRuntime;
use Tragwerk\Domain\Enum\MountSource;
use Tragwerk\Domain\Enum\RouteType;
use Tragwerk\Domain\Enum\ServiceRuntime;
use Tragwerk\Domain\Model\ApplicationConfig;
use Tragwerk\Domain\Model\HookConfig;
use Tragwerk\Domain\Model\LocationConfig;
use Tragwerk\Domain\Model\MountConfig;
use Tragwerk\Domain\Model\ProjectConfig;
use Tragwerk\Domain\Model\RelationshipConfig;
use Tragwerk\Domain\Model\RouteConfig;
use Tragwerk\Domain\Model\ServiceConfig;
use Tragwerk\Domain\Model\WebConfig;

final class DockerComposeGeneratorTest extends TestCase
{
    private DockerComposeGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new DockerComposeGenerator(new ServiceImageResolver());
    }

    /**
     * @param list<RelationshipConfig> $relationships
     * @param list<MountConfig>        $mounts
     * @param list<HookConfig>         $hooks
     */
    private static function app(
        string $name,
        ApplicationRuntime $type = ApplicationRuntime::PHP85,
        array $relationships = [],
        array $mounts = [],
        array $hooks = [],
    ): ApplicationConfig {
        return new ApplicationConfig(
            name: $name,
            type: $type,
            root: '/',
            web: new WebConfig([new LocationConfig(path: '/', root: 'public', index: 'index.php', passthru: null)]),
            hooks: $hooks,
            mounts: $mounts,
            relationships: $relationships,
        );
    }

    private static function upstream(string $pattern, string $upstream): RouteConfig
    {
        return new RouteConfig(pattern: $pattern, type: RouteType::UPSTREAM, upstream: $upstream, to: null);
    }

    private static function redirect(string $pattern, string $to): RouteConfig
    {
        return new RouteConfig(pattern: $pattern, type: RouteType::REDIRECT, upstream: null, to: $to);
    }

    /**
     * @param list<ApplicationConfig> $applications
     * @param list<RouteConfig>       $routes
     * @param list<ServiceConfig>     $services
     */
    private static function project(array $applications, array $routes, array $services = []): ProjectConfig
    {
        return new ProjectConfig(applications: $applications, routes: $routes, services: $services);
    }

    /**
     * @param array<string, mixed> $compose
     *
     * @return array<string, mixed>
     */
    private function service(array $compose, string $name): array
    {
        /** @var array<string, mixed>|null $svc */
        $svc = $compose['services'][$name] ?? null;
        self::assertIsArray($svc, 'Service \'' . $name . '\' not found in compose output');

        return $svc;
    }

    /**
     * @param array<string, mixed> $compose
     *
     * @return list<string>
     */
    private function labels(array $compose, string $name): array
    {
        $svc = $this->service($compose, $name);
        /** @var list<string>|null $labels */
        $labels = $svc['labels'] ?? null;
        self::assertIsArray($labels, 'Service \'' . $name . '\' has no labels');

        return $labels;
    }

    /**
     * @param array<string, mixed> $compose
     *
     * @return list<string>
     */
    private function serviceVolumes(array $compose, string $name): array
    {
        $svc = $this->service($compose, $name);
        /** @var list<string>|null $volumes */
        $volumes = $svc['volumes'] ?? null;
        self::assertIsArray($volumes, 'Service \'' . $name . '\' has no volumes');

        return $volumes;
    }

    /**
     * @param array<string, mixed> $compose
     *
     * @return array<string, string>
     */
    private function environment(array $compose, string $name): array
    {
        $svc = $this->service($compose, $name);
        /** @var array<string, string>|null $env */
        $env = $svc['environment'] ?? null;
        self::assertIsArray($env, 'Service \'' . $name . '\' has no environment');

        return $env;
    }

    #[Test]
    public function singleAppGetsTraefikLabels(): void
    {
        $config  = self::project([self::app('app')], [self::upstream('https://{default}', 'app:http')]);
        $compose = $this->generator->generate($config);

        $labels = $this->labels($compose, 'app');
        self::assertContains('traefik.enable=true', $labels);
        self::assertContains('traefik.http.routers.app-https-0.rule=Host(`${DOMAIN:-localhost}`)', $labels);
        self::assertContains('traefik.http.routers.app-https-0.entrypoints=websecure', $labels);
        self::assertContains('traefik.http.routers.app-https-0.tls.certresolver=letsencrypt', $labels);
    }

    #[Test]
    public function twoAppsEachGetOwnTraefikLabels(): void
    {
        $config = self::project(
            [self::app('app'), self::app('foobar')],
            [
                self::upstream('https://{default}', 'app:http'),
                self::upstream('https://foobar.{default}', 'foobar:http'),
            ],
        );

        $compose      = $this->generator->generate($config);
        $appLabels    = $this->labels($compose, 'app');
        $foobarLabels = $this->labels($compose, 'foobar');

        self::assertContains('traefik.enable=true', $appLabels);
        self::assertContains('traefik.http.routers.app-https-0.rule=Host(`${DOMAIN:-localhost}`)', $appLabels);

        self::assertContains('traefik.enable=true', $foobarLabels);
        self::assertContains(
            'traefik.http.routers.foobar-https-0.rule=Host(`foobar.${DOMAIN:-localhost}`)',
            $foobarLabels,
        );
    }

    #[Test]
    public function appWithNoMatchingRouteGetsNoLabels(): void
    {
        $config  = self::project(
            [self::app('main'), self::app('worker')],
            [self::upstream('https://{default}', 'main:http')],
        );
        $compose = $this->generator->generate($config);
        $worker  = $this->service($compose, 'worker');

        self::assertArrayNotHasKey('labels', $worker);
    }

    #[Test]
    public function unknownUpstreamFallsBackToFirstApp(): void
    {
        $config  = self::project(
            [self::app('app'), self::app('other')],
            [self::upstream('https://{default}', 'unknown:http')],
        );
        $compose = $this->generator->generate($config);
        $app     = $this->service($compose, 'app');
        $other   = $this->service($compose, 'other');

        self::assertArrayHasKey('labels', $app);
        self::assertArrayNotHasKey('labels', $other);
    }

    #[Test]
    public function redirectRouteAddsMiddlewareLabels(): void
    {
        $config = self::project(
            [self::app('app')],
            [
                self::upstream('https://{default}', 'app:http'),
                self::redirect('http://{default}', 'https://{default}'),
            ],
        );

        $compose = $this->generator->generate($config);
        $labels  = $this->labels($compose, 'app');

        self::assertContains(
            'traefik.http.middlewares.app-redirect-to-https.redirectscheme.scheme=https',
            $labels,
        );
        self::assertContains(
            'traefik.http.middlewares.app-redirect-to-https.redirectscheme.permanent=true',
            $labels,
        );
        self::assertContains('traefik.http.routers.app-http-0.middlewares=app-redirect-to-https', $labels);
    }

    #[Test]
    public function traefikServiceAlwaysPresent(): void
    {
        $config  = self::project([self::app('app')], [self::upstream('https://{default}', 'app:http')]);
        $compose = $this->generator->generate($config);
        $traefik = $this->service($compose, 'traefik');

        self::assertSame('traefik:v3', $traefik['image']);
        self::assertContains('traefik-certs:/certs', $this->serviceVolumes($compose, 'traefik'));
    }

    #[Test]
    public function localMountCreatesNamedVolume(): void
    {
        $mount  = new MountConfig(name: 'storage', source: MountSource::LOCAL, path: 'storage', cloneFromParent: false);
        $config = self::project(
            [self::app('app', mounts: [$mount])],
            [self::upstream('https://{default}', 'app:http')],
        );

        $compose = $this->generator->generate($config);
        $volumes = $compose['volumes'];
        self::assertIsArray($volumes);

        self::assertContains('app-storage:/app/storage', $this->serviceVolumes($compose, 'app'));
        self::assertArrayHasKey('app-storage', $volumes);
    }

    #[Test]
    public function postgresqlServiceGetsDataVolume(): void
    {
        $service = new ServiceConfig(name: 'db', type: ServiceRuntime::POSTGRES18, disk: 2048);
        $config  = self::project(
            [self::app('app')],
            [self::upstream('https://{default}', 'app:http')],
            [$service],
        );

        $compose = $this->generator->generate($config);

        self::assertSame('postgres:18', $this->service($compose, 'db')['image']);
        self::assertContains('db-data:/var/lib/postgresql/data', $this->serviceVolumes($compose, 'db'));
        self::assertSame('app', $this->environment($compose, 'db')['POSTGRES_DB']);
    }

    #[Test]
    public function relationshipInjectsEnvVar(): void
    {
        $rel    = new RelationshipConfig(name: 'database', target: 'db');
        $svc    = new ServiceConfig(name: 'db', type: ServiceRuntime::POSTGRES18, disk: null);
        $config = self::project(
            [self::app('app', relationships: [$rel])],
            [self::upstream('https://{default}', 'app:http')],
            [$svc],
        );

        $compose = $this->generator->generate($config);
        $env     = $this->environment($compose, 'app');

        self::assertArrayHasKey('DATABASE_URL', $env);
        self::assertStringContainsString('postgresql://', $env['DATABASE_URL']);
    }

    #[Test]
    public function appServiceUsesBuildKeyWithDockerfileName(): void
    {
        $config  = self::project([self::app('my-app')], [self::upstream('https://{default}', 'my-app:http')]);
        $compose = $this->generator->generate($config);
        $svc     = $this->service($compose, 'my-app');

        self::assertArrayHasKey('build', $svc);
        /** @var array<string, string> $build */
        $build = $svc['build'];
        self::assertSame('.', $build['context']);
        self::assertSame('Dockerfile.my-app', $build['dockerfile']);
    }

    #[Test]
    public function appServiceHasNoImageKey(): void
    {
        $config = self::project([self::app('app')], [self::upstream('https://{default}', 'app:http')]);
        $svc    = $this->service($this->generator->generate($config), 'app');

        self::assertArrayNotHasKey('image', $svc);
    }

    #[Test]
    public function appServiceHasNoSourceMount(): void
    {
        $config  = self::project([self::app('app')], [self::upstream('https://{default}', 'app:http')]);
        $compose = $this->generator->generate($config);
        $svc     = $this->service($compose, 'app');

        self::assertArrayNotHasKey('volumes', $svc);
    }

    public static function slugifyProvider(): Generator
    {
        yield 'spaces to dashes'       => ['My Test Project', 'my-test-project'];
        yield 'underscores to dashes'  => ['my_project', 'my-project'];
        yield 'mixed case'             => ['FooBar', 'foobar'];
        yield 'already slug'           => ['app', 'app'];
        yield 'special chars stripped' => ['app.v2', 'appv2'];
    }

    #[Test]
    #[DataProvider('slugifyProvider')]
    public function slugify(string $input, string $expected): void
    {
        self::assertSame($expected, $this->generator->slugify($input));
    }
}
