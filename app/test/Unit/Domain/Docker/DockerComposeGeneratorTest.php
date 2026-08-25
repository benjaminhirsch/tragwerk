<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\Docker;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Tragwerk\Domain\Docker\DockerComposeGenerator;
use Tragwerk\Domain\Docker\ServiceImageResolver;
use Tragwerk\Domain\Enum\ApplicationRuntime;
use Tragwerk\Domain\Enum\MountSource;
use Tragwerk\Domain\Enum\ServiceRuntime;
use Tragwerk\Domain\Model\ApplicationConfig;
use Tragwerk\Domain\Model\CronDefinitionConfig;
use Tragwerk\Domain\Model\HealthConfig;
use Tragwerk\Domain\Model\HookConfig;
use Tragwerk\Domain\Model\LocationConfig;
use Tragwerk\Domain\Model\MountConfig;
use Tragwerk\Domain\Model\PlatformContext;
use Tragwerk\Domain\Model\ProjectConfig;
use Tragwerk\Domain\Model\RelationshipConfig;
use Tragwerk\Domain\Model\RouteConfig;
use Tragwerk\Domain\Model\ServiceConfig;
use Tragwerk\Domain\Model\WebConfig;
use Tragwerk\Domain\Model\WorkerConfig;
use Tragwerk\Domain\Model\WorkerDefinitionConfig;

use function base64_decode;
use function json_decode;

use const JSON_THROW_ON_ERROR;

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
        return new RouteConfig(pattern: $pattern, upstream: $upstream);
    }

    private static function workerApp(string $name, WorkerConfig $worker): ApplicationConfig
    {
        return new ApplicationConfig(
            name: $name,
            type: ApplicationRuntime::PHP85,
            root: '/',
            web: new WebConfig([new LocationConfig(path: '/', root: 'public', passthru: '/index.php')]),
            workerMode: $worker,
        );
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
    public function workerMaxRequestsBecomesEnvVar(): void
    {
        $config  = self::project(
            [self::workerApp('app', new WorkerConfig(count: 4, maxRequests: 1000))],
            [self::upstream('https://{default}', 'app:http')],
        );
        $compose = $this->generator->generate($config, 'main');

        self::assertSame('1000', $this->environment($compose, 'app')['MAX_REQUESTS']);
    }

    #[Test]
    public function workerWithoutMaxRequestsHasNoEnvVar(): void
    {
        $config  = self::project(
            [self::workerApp('app', new WorkerConfig(count: 4))],
            [self::upstream('https://{default}', 'app:http')],
        );
        $compose = $this->generator->generate($config, 'main');

        self::assertArrayNotHasKey('MAX_REQUESTS', $this->environment($compose, 'app'));
    }

    #[Test]
    public function nonWorkerAppHasNoMaxRequestsEnvVar(): void
    {
        $config  = self::project([self::app('app')], [self::upstream('https://{default}', 'app:http')]);
        $compose = $this->generator->generate($config, 'main');

        self::assertArrayNotHasKey('MAX_REQUESTS', $this->environment($compose, 'app'));
    }

    #[Test]
    public function singleAppGetsTraefikLabels(): void
    {
        $config  = self::project([self::app('app')], [self::upstream('https://{default}', 'app:http')]);
        $compose = $this->generator->generate($config, 'main');

        $labels = $this->labels($compose, 'app');
        self::assertContains('traefik.enable=true', $labels);
        self::assertContains('traefik.http.routers.app-main-https-0.rule=Host(`${DEFAULT:-localhost}`)', $labels);
        self::assertContains('traefik.http.routers.app-main-https-0.entrypoints=websecure', $labels);
        self::assertContains('traefik.http.routers.app-main-https-0.tls.certresolver=letsencrypt', $labels);
    }

    #[Test]
    public function routerNamesArePrefixedWithBranch(): void
    {
        $config  = self::project([self::app('app')], [self::upstream('https://{default}', 'app:http')]);
        $main    = $this->generator->generate($config, 'main');
        $staging = $this->generator->generate($config, 'staging');

        self::assertContains(
            'traefik.http.routers.app-main-https-0.rule=Host(`${DEFAULT:-localhost}`)',
            $this->labels($main, 'app'),
        );
        self::assertContains(
            'traefik.http.routers.app-staging-https-0.rule=Host(`${DEFAULT:-localhost}`)',
            $this->labels($staging, 'app'),
        );
    }

    #[Test]
    public function appContainerIsOnTragwerkNet(): void
    {
        $config  = self::project([self::app('app')], [self::upstream('https://{default}', 'app:http')]);
        $compose = $this->generator->generate($config, 'main');

        $svc = $this->service($compose, 'app');
        /** @var mixed $networks */
        $networks = $svc['networks'];
        self::assertIsArray($networks);
        self::assertContains('tragwerk-net', $networks);
        self::assertContains('default', $networks);

        self::assertIsArray($compose['networks'] ?? null);
        self::assertArrayHasKey('tragwerk-net', $compose['networks']);
        self::assertTrue($compose['networks']['tragwerk-net']['external']);
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

        $compose      = $this->generator->generate($config, 'main');
        $appLabels    = $this->labels($compose, 'app');
        $foobarLabels = $this->labels($compose, 'foobar');

        self::assertContains('traefik.enable=true', $appLabels);
        self::assertContains('traefik.http.routers.app-main-https-0.rule=Host(`${DEFAULT:-localhost}`)', $appLabels);

        self::assertContains('traefik.enable=true', $foobarLabels);
        self::assertContains(
            'traefik.http.routers.foobar-main-https-0.rule=Host(`foobar.${DEFAULT:-localhost}`)',
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
    public function noTraefikServiceInGeneratedCompose(): void
    {
        $config  = self::project([self::app('app')], [self::upstream('https://{default}', 'app:http')]);
        $compose = $this->generator->generate($config, 'main');

        /** @var mixed $services */
        $services = $compose['services'];
        self::assertIsArray($services);
        self::assertArrayNotHasKey('traefik', $services);
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
        self::assertContains('db-data:/var/lib/postgresql', $this->serviceVolumes($compose, 'db'));
        self::assertSame('app', $this->environment($compose, 'db')['POSTGRES_DB']);
    }

    #[Test]
    public function postgresqlPre18UsesDataSubdirectory(): void
    {
        $service = new ServiceConfig(name: 'db', type: ServiceRuntime::POSTGRES16, disk: 2048);
        $config  = self::project(
            [self::app('app')],
            [self::upstream('https://{default}', 'app:http')],
            [$service],
        );

        $compose = $this->generator->generate($config);

        self::assertContains('db-data:/var/lib/postgresql/data', $this->serviceVolumes($compose, 'db'));
    }

    #[Test]
    public function postgresRelationshipInjectsIndividualEnvVars(): void
    {
        $rel    = new RelationshipConfig(name: 'database', target: 'db');
        $svc    = new ServiceConfig(name: 'db', type: ServiceRuntime::POSTGRES18, disk: null);
        $config = self::project(
            [self::app('app', relationships: [$rel])],
            [self::upstream('https://{default}', 'app:http')],
            [$svc],
        );

        $env = $this->environment($this->generator->generate($config), 'app');

        self::assertSame('db', $env['TRAGWERK_DATABASE_HOST']);
        self::assertSame('5432', $env['TRAGWERK_DATABASE_PORT']);
        self::assertSame('app', $env['TRAGWERK_DATABASE_DATABASE']);
        self::assertSame('app', $env['TRAGWERK_DATABASE_USER']);
        self::assertSame('secret', $env['TRAGWERK_DATABASE_PASSWORD']);
        self::assertArrayNotHasKey('DATABASE_URL', $env);
    }

    #[Test]
    public function mysqlRelationshipInjectsIndividualEnvVars(): void
    {
        $rel    = new RelationshipConfig(name: 'db', target: 'mysql');
        $svc    = new ServiceConfig(name: 'mysql', type: ServiceRuntime::MYSQL8, disk: null);
        $config = self::project(
            [self::app('app', relationships: [$rel])],
            [self::upstream('https://{default}', 'app:http')],
            [$svc],
        );

        $env = $this->environment($this->generator->generate($config), 'app');

        self::assertSame('mysql', $env['TRAGWERK_DB_HOST']);
        self::assertSame('3306', $env['TRAGWERK_DB_PORT']);
        self::assertSame('app', $env['TRAGWERK_DB_DATABASE']);
        self::assertSame('app', $env['TRAGWERK_DB_USER']);
        self::assertSame('secret', $env['TRAGWERK_DB_PASSWORD']);
    }

    #[Test]
    public function redisRelationshipInjectsHostAndPort(): void
    {
        $rel    = new RelationshipConfig(name: 'cache', target: 'redis');
        $svc    = new ServiceConfig(name: 'redis', type: ServiceRuntime::REDIS8, disk: null);
        $config = self::project(
            [self::app('app', relationships: [$rel])],
            [self::upstream('https://{default}', 'app:http')],
            [$svc],
        );

        $env = $this->environment($this->generator->generate($config), 'app');

        self::assertSame('redis', $env['TRAGWERK_CACHE_HOST']);
        self::assertSame('6379', $env['TRAGWERK_CACHE_PORT']);
        self::assertArrayNotHasKey('TRAGWERK_CACHE_DATABASE', $env);
        self::assertArrayNotHasKey('TRAGWERK_CACHE_USER', $env);
    }

    #[Test]
    public function serviceWithoutLocalPortHasNoPortsMapping(): void
    {
        $service = new ServiceConfig(name: 'db', type: ServiceRuntime::POSTGRES18, disk: null);
        $config  = self::project(
            [self::app('app')],
            [self::upstream('https://{default}', 'app:http')],
            [$service],
        );

        self::assertArrayNotHasKey('ports', $this->service($this->generator->generate($config), 'db'));
    }

    #[Test]
    public function postgresqlLocalPortBindsLoopbackOnMain(): void
    {
        $service = new ServiceConfig(name: 'db', type: ServiceRuntime::POSTGRES18, disk: null, localPort: 55432);
        $config  = self::project(
            [self::app('app')],
            [self::upstream('https://{default}', 'app:http')],
            [$service],
        );

        self::assertSame(
            ['127.0.0.1:55432:5432'],
            $this->service($this->generator->generate($config, 'main'), 'db')['ports'],
        );
    }

    #[Test]
    public function masterBranchAlsoUsesConfiguredPort(): void
    {
        $service = new ServiceConfig(name: 'db', type: ServiceRuntime::POSTGRES18, disk: null, localPort: 55432);
        $config  = self::project(
            [self::app('app')],
            [self::upstream('https://{default}', 'app:http')],
            [$service],
        );

        self::assertSame(
            ['127.0.0.1:55432:5432'],
            $this->service($this->generator->generate($config, 'master'), 'db')['ports'],
        );
    }

    #[Test]
    public function featureBranchGetsDockerAssignedLoopbackPort(): void
    {
        $service = new ServiceConfig(name: 'db', type: ServiceRuntime::POSTGRES18, disk: null, localPort: 55432);
        $config  = self::project(
            [self::app('app')],
            [self::upstream('https://{default}', 'app:http')],
            [$service],
        );

        // Non-root branch: empty host-port slot -> Docker assigns a free loopback port.
        self::assertSame(
            ['127.0.0.1::5432'],
            $this->service($this->generator->generate($config, 'feature-x'), 'db')['ports'],
        );
    }

    #[Test]
    public function mysqlLocalPortMapsToInternal3306(): void
    {
        $service = new ServiceConfig(name: 'mysql', type: ServiceRuntime::MYSQL8, disk: null, localPort: 53306);
        $config  = self::project(
            [self::app('app')],
            [self::upstream('https://{default}', 'app:http')],
            [$service],
        );

        self::assertSame(
            ['127.0.0.1:53306:3306'],
            $this->service($this->generator->generate($config, 'main'), 'mysql')['ports'],
        );
    }

    #[Test]
    public function redisLocalPortMapsToInternal6379(): void
    {
        $service = new ServiceConfig(name: 'redis', type: ServiceRuntime::REDIS8, disk: null, localPort: 56379);
        $config  = self::project(
            [self::app('app')],
            [self::upstream('https://{default}', 'app:http')],
            [$service],
        );

        $ports = $this->service($this->generator->generate($config, 'main'), 'redis')['ports'];

        self::assertSame(['127.0.0.1:56379:6379'], $ports);
        self::assertIsArray($ports);
        self::assertStringStartsWith('127.0.0.1:', $ports[0]);
    }

    #[Test]
    public function relationshipNameWithHyphensNormalisedInEnvVarPrefix(): void
    {
        $rel    = new RelationshipConfig(name: 'primary-db', target: 'db');
        $svc    = new ServiceConfig(name: 'db', type: ServiceRuntime::POSTGRES18, disk: null);
        $config = self::project(
            [self::app('app', relationships: [$rel])],
            [self::upstream('https://{default}', 'app:http')],
            [$svc],
        );

        $env = $this->environment($this->generator->generate($config), 'app');

        self::assertArrayHasKey('TRAGWERK_PRIMARY_DB_HOST', $env);
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

    #[Test]
    public function postgresqlServiceHasHealthcheck(): void
    {
        $service = new ServiceConfig(name: 'db', type: ServiceRuntime::POSTGRES18, disk: 2048);
        $config  = self::project(
            [self::app('app')],
            [self::upstream('https://{default}', 'app:http')],
            [$service],
        );

        $db          = $this->service($this->generator->generate($config), 'db');
        $healthcheck = $db['healthcheck'];

        self::assertIsArray($healthcheck);
        self::assertSame(['CMD-SHELL', 'pg_isready -U app -d app'], $healthcheck['test']);
        self::assertArrayHasKey('interval', $healthcheck);
        self::assertArrayHasKey('retries', $healthcheck);
    }

    #[Test]
    public function redisServiceHasHealthcheck(): void
    {
        $service = new ServiceConfig(name: 'redis', type: ServiceRuntime::REDIS8, disk: null);
        $config  = self::project(
            [self::app('app')],
            [self::upstream('https://{default}', 'app:http')],
            [$service],
        );

        $db          = $this->service($this->generator->generate($config), 'redis');
        $healthcheck = $db['healthcheck'];

        self::assertIsArray($healthcheck);
        self::assertSame(['CMD', 'redis-cli', 'ping'], $healthcheck['test']);
    }

    #[Test]
    public function appDependsOnServiceWithHealthyCondition(): void
    {
        $rel    = new RelationshipConfig(name: 'database', target: 'db');
        $svc    = new ServiceConfig(name: 'db', type: ServiceRuntime::POSTGRES18, disk: null);
        $config = self::project(
            [self::app('app', relationships: [$rel])],
            [self::upstream('https://{default}', 'app:http')],
            [$svc],
        );

        $svc       = $this->service($this->generator->generate($config), 'app');
        $dependsOn = $svc['depends_on'];

        self::assertIsArray($dependsOn);
        self::assertArrayHasKey('db', $dependsOn);
        self::assertSame('service_healthy', $dependsOn['db']['condition']);
    }

    #[Test]
    public function phpAppServiceIsReadOnly(): void
    {
        $config = self::project([self::app('app')], [self::upstream('https://{default}', 'app:http')]);
        $svc    = $this->service($this->generator->generate($config), 'app');

        self::assertTrue($svc['read_only']);
    }

    #[Test]
    public function phpAppServiceHasTmpfsForWritableScratch(): void
    {
        $config = self::project([self::app('app')], [self::upstream('https://{default}', 'app:http')]);
        $svc    = $this->service($this->generator->generate($config), 'app');

        self::assertIsArray($svc['tmpfs']);
        self::assertContains('/tmp', $svc['tmpfs']);
        self::assertContains('/data', $svc['tmpfs']);
        self::assertContains('/config', $svc['tmpfs']);
    }

    #[Test]
    public function phpAppServiceHasHttpHealthcheck(): void
    {
        $config      = self::project([self::app('app')], [self::upstream('https://{default}', 'app:http')]);
        $svc         = $this->service($this->generator->generate($config), 'app');
        $healthcheck = $svc['healthcheck'];

        self::assertIsArray($healthcheck);
        self::assertIsArray($healthcheck['test']);
        self::assertSame('CMD-SHELL', $healthcheck['test'][0]);
        self::assertIsString($healthcheck['test'][1]);
        self::assertStringContainsString('stream_socket_client', $healthcheck['test'][1]);
        self::assertSame('30s', $healthcheck['start_period']);
        self::assertSame(12, $healthcheck['retries']);
    }

    #[Test]
    public function healthElementWidensTheStartupWindow(): void
    {
        // A deploy hook runs before the web server listens, so a slow one needs a
        // longer window or the container is torn down mid-hook.
        $app = new ApplicationConfig(
            name: 'app',
            type: ApplicationRuntime::PHP85,
            root: '/',
            web: new WebConfig([new LocationConfig(path: '/', root: 'public', passthru: '/index.php')]),
            health: new HealthConfig(startPeriod: '300s', retries: 60),
        );

        $config      = self::project([$app], [self::upstream('https://{default}', 'app:http')]);
        $svc         = $this->service($this->generator->generate($config), 'app');
        $healthcheck = $svc['healthcheck'];

        self::assertIsArray($healthcheck);
        self::assertSame('300s', $healthcheck['start_period']);
        self::assertSame(60, $healthcheck['retries']);
    }

    #[Test]
    public function backingServiceIsNotReadOnly(): void
    {
        $service = new ServiceConfig(name: 'db', type: ServiceRuntime::POSTGRES18, disk: 2048);
        $config  = self::project(
            [self::app('app')],
            [self::upstream('https://{default}', 'app:http')],
            [$service],
        );

        $db = $this->service($this->generator->generate($config), 'db');

        self::assertArrayNotHasKey('read_only', $db);
    }

    #[Test]
    public function imageTagsReplacesBuildWithImageKey(): void
    {
        $config = self::project([self::app('app')], [self::upstream('https://{default}', 'app:http')]);
        $tags   = ['app' => 'ghcr.io/myorg/myapp/app:main-abc12345'];

        $compose = $this->generator->generate($config, 'main', [], $tags);
        $svc     = $this->service($compose, 'app');

        self::assertSame('ghcr.io/myorg/myapp/app:main-abc12345', $svc['image']);
        self::assertArrayNotHasKey('build', $svc);
    }

    #[Test]
    public function noImageTagsUsesBuildKey(): void
    {
        $config  = self::project([self::app('app')], [self::upstream('https://{default}', 'app:http')]);
        $compose = $this->generator->generate($config, 'main');
        $svc     = $this->service($compose, 'app');

        self::assertArrayHasKey('build', $svc);
        self::assertArrayNotHasKey('image', $svc);
    }

    #[Test]
    public function serviceMountCreatesNamedVolume(): void
    {
        $mount   = new MountConfig(name: 'uploads', source: MountSource::SERVICE, path: 'data/uploads');
        $config  = self::project([self::app('app', mounts: [$mount])], []);
        $compose = $this->generator->generate($config);

        /** @var array<string, mixed> $volumes */
        $volumes = $compose['volumes'] ?? [];
        self::assertArrayHasKey('app-uploads', $volumes);
        self::assertContains('app-uploads:/app/data/uploads', $this->serviceVolumes($compose, 'app'));
    }

    #[Test]
    public function serviceMountIsInheritedByWorker(): void
    {
        $mount   = new MountConfig(name: 'uploads', source: MountSource::SERVICE, path: 'data/uploads');
        $worker  = new WorkerDefinitionConfig(name: 'queue', command: 'bin/cli worker:start default');
        $app     = new ApplicationConfig(
            name: 'app',
            type: ApplicationRuntime::PHP85,
            root: '/',
            web: new WebConfig([new LocationConfig(path: '/', root: 'public', passthru: '/index.php')]),
            mounts: [$mount],
            workers: [$worker],
        );
        $config  = self::project([$app], [self::upstream('https://{default}', 'app:http')]);
        $compose = $this->generator->generate($config);

        self::assertContains('app-uploads:/app/data/uploads', $this->serviceVolumes($compose, 'app-worker-queue'));
    }

    #[Test]
    public function appServiceHasNoDeployBlock(): void
    {
        $config  = self::project([self::app('app')], []);
        $compose = $this->generator->generate($config);
        $svc     = $this->service($compose, 'app');

        self::assertArrayNotHasKey('deploy', $svc);
    }

    #[Test]
    public function backgroundWorkersGenerateSeparateServices(): void
    {
        $app     = new ApplicationConfig(
            name: 'app',
            type: ApplicationRuntime::PHP85,
            root: '/',
            web: new WebConfig([new LocationConfig(path: '/', root: 'public', passthru: '/index.php')]),
            workers: [
                new WorkerDefinitionConfig(name: 'queue', command: 'bin/cli worker:start default'),
                new WorkerDefinitionConfig(name: 'metrics', command: 'bin/cli metrics:sample'),
            ],
        );
        $config  = self::project([$app], [self::upstream('https://{default}', 'app:http')]);
        $compose = $this->generator->generate($config, 'main');

        $queue   = $this->service($compose, 'app-worker-queue');
        $metrics = $this->service($compose, 'app-worker-metrics');

        // The command itself lives in a script baked into the image (see DockerfileGenerator),
        // so compose only points at it.
        self::assertSame('/usr/local/bin/tragwerk-worker-queue', $queue['command']);
        self::assertSame('/usr/local/bin/tragwerk-worker-metrics', $metrics['command']);
        self::assertSame('unless-stopped', $queue['restart']);
        self::assertSame('unless-stopped', $metrics['restart']);
    }

    #[Test]
    public function cronsGenerateSingleSidecarRunningSupercronic(): void
    {
        $app     = new ApplicationConfig(
            name: 'app',
            type: ApplicationRuntime::PHP85,
            root: '/',
            web: new WebConfig([new LocationConfig(path: '/', root: 'public', passthru: '/index.php')]),
            crons: [
                new CronDefinitionConfig(name: 'cleanup', command: 'bin/cli app:cleanup', schedule: '0 2 * * *'),
                new CronDefinitionConfig(name: 'import', command: 'bin/cli app:import', schedule: '@hourly'),
            ],
        );
        $config  = self::project([$app], [self::upstream('https://{default}', 'app:http')]);
        $compose = $this->generator->generate($config, 'main');

        $cron = $this->service($compose, 'app-cron');

        self::assertSame('supercronic -no-reap -json /etc/supercronic/crontab', $cron['command']);
        self::assertSame('unless-stopped', $cron['restart']);
        self::assertArrayNotHasKey('labels', $cron);
        self::assertSame(
            ['CMD', 'supercronic', '-test', '/etc/supercronic/crontab'],
            $cron['healthcheck']['test'] ?? null,
        );
    }

    #[Test]
    public function noCronSidecarWhenNoCronsDefined(): void
    {
        $config  = self::project([self::app('app')], []);
        $compose = $this->generator->generate($config);

        $services = $compose['services'];
        self::assertIsArray($services);
        self::assertArrayNotHasKey('app-cron', $services);
    }

    #[Test]
    public function sidecarsDropTheImageEntrypoint(): void
    {
        // The image ENTRYPOINT runs the deploy hooks. Those belong to the deployment,
        // not to every container sharing the image — a worker restarting on its own
        // would otherwise re-run migrations behind the application's back.
        $app     = new ApplicationConfig(
            name: 'app',
            type: ApplicationRuntime::PHP85,
            root: '/',
            web: new WebConfig([new LocationConfig(path: '/', root: 'public', passthru: '/index.php')]),
            workers: [new WorkerDefinitionConfig(name: 'queue', command: 'bin/cli worker:start default')],
            crons: [
                new CronDefinitionConfig(name: 'cleanup', command: 'bin/cli app:cleanup', schedule: '0 2 * * *'),
            ],
        );
        $config  = self::project([$app], [self::upstream('https://{default}', 'app:http')]);
        $compose = $this->generator->generate($config, 'main');

        self::assertSame([], $this->service($compose, 'app-worker-queue')['entrypoint']);
        self::assertSame([], $this->service($compose, 'app-cron')['entrypoint']);
        // The application itself keeps it — that is where the hooks must run.
        self::assertArrayNotHasKey('entrypoint', $this->service($compose, 'app'));
    }

    #[Test]
    public function sidecarEntrypointSurvivesTheYamlDump(): void
    {
        // Without DUMP_EMPTY_ARRAY_AS_SEQUENCE the dumper writes "entrypoint: {}",
        // which compose rejects — the flag at every dump call site is load-bearing.
        $app     = new ApplicationConfig(
            name: 'app',
            type: ApplicationRuntime::PHP85,
            root: '/',
            web: new WebConfig([new LocationConfig(path: '/', root: 'public', passthru: '/index.php')]),
            workers: [new WorkerDefinitionConfig(name: 'queue', command: 'bin/cli worker:start default')],
        );
        $config  = self::project([$app], [self::upstream('https://{default}', 'app:http')]);
        $compose = $this->generator->generate($config, 'main');

        $yaml = Yaml::dump($compose, 10, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);

        self::assertStringContainsString('entrypoint: []', $yaml);
        self::assertStringNotContainsString('entrypoint: {}', $yaml);
    }

    #[Test]
    public function backgroundWorkerHasNoTraefikLabels(): void
    {
        $app     = new ApplicationConfig(
            name: 'app',
            type: ApplicationRuntime::PHP85,
            root: '/',
            web: new WebConfig([new LocationConfig(path: '/', root: 'public', passthru: '/index.php')]),
            workers: [new WorkerDefinitionConfig(name: 'queue', command: 'bin/cli worker:start default')],
        );
        $config  = self::project([$app], [self::upstream('https://{default}', 'app:http')]);
        $compose = $this->generator->generate($config, 'main');

        $worker = $this->service($compose, 'app-worker-queue');
        self::assertArrayNotHasKey('labels', $worker);
        self::assertSame(['disable' => true], $worker['healthcheck'] ?? null);
    }

    #[Test]
    public function backgroundWorkerInheritsEnvironmentAndVolumes(): void
    {
        $rel     = new RelationshipConfig(name: 'database', target: 'db');
        $svc     = new ServiceConfig(name: 'db', type: ServiceRuntime::POSTGRES18, disk: null);
        $mount   = new MountConfig(
            name: 'storage',
            source: MountSource::LOCAL,
            path: 'storage',
            cloneFromParent: false,
        );
        $app     = new ApplicationConfig(
            name: 'app',
            type: ApplicationRuntime::PHP85,
            root: '/',
            web: new WebConfig([new LocationConfig(path: '/', root: 'public', passthru: '/index.php')]),
            mounts: [$mount],
            relationships: [$rel],
            workers: [new WorkerDefinitionConfig(name: 'queue', command: 'bin/cli worker:start default')],
        );
        $config  = self::project([$app], [self::upstream('https://{default}', 'app:http')], [$svc]);
        $compose = $this->generator->generate($config, 'main');

        $worker = $this->service($compose, 'app-worker-queue');
        /** @var array<string, string> $env */
        $env = $worker['environment'];
        self::assertSame('db', $env['TRAGWERK_DATABASE_HOST']);
        self::assertContains('app-storage:/app/storage', $this->serviceVolumes($compose, 'app-worker-queue'));
        /** @var list<string> $networks */
        $networks = $worker['networks'];
        self::assertContains('tragwerk-net', $networks);
    }

    #[Test]
    public function backgroundWorkerInheritsBuildConfig(): void
    {
        $app     = new ApplicationConfig(
            name: 'app',
            type: ApplicationRuntime::PHP85,
            root: '/',
            web: new WebConfig([new LocationConfig(path: '/', root: 'public', passthru: '/index.php')]),
            workers: [new WorkerDefinitionConfig(name: 'queue', command: 'bin/cli worker:start default')],
        );
        $config  = self::project([$app], [self::upstream('https://{default}', 'app:http')]);
        $compose = $this->generator->generate($config, 'main');

        $worker = $this->service($compose, 'app-worker-queue');
        self::assertArrayHasKey('build', $worker);
        self::assertSame('Dockerfile.app', $worker['build']['dockerfile']);
    }

    #[Test]
    public function backgroundWorkerWithImageTagUsesImageKey(): void
    {
        $app     = new ApplicationConfig(
            name: 'app',
            type: ApplicationRuntime::PHP85,
            root: '/',
            web: new WebConfig([new LocationConfig(path: '/', root: 'public', passthru: '/index.php')]),
            workers: [new WorkerDefinitionConfig(name: 'queue', command: 'bin/cli worker:start default')],
        );
        $config  = self::project([$app], [self::upstream('https://{default}', 'app:http')]);
        $compose = $this->generator->generate($config, 'main', [], ['app' => 'ghcr.io/myorg/myapp:main']);

        $worker = $this->service($compose, 'app-worker-queue');
        self::assertSame('ghcr.io/myorg/myapp:main', $worker['image']);
        self::assertArrayNotHasKey('build', $worker);
    }

    private static function platform(string $branch = 'main', string|null $deployId = 'job-1'): PlatformContext
    {
        return new PlatformContext(
            projectId:      'c0ffee00-1111-2222-3333-444455556666',
            projectName:    'My Shop',
            projectEntropy: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            branch:         $branch,
            commitSha:      'abc1234',
            deployId:       $deployId,
        );
    }

    /** @param array<string, string> $env */
    private static function blob(array $env, string $key): mixed
    {
        $raw = base64_decode($env[$key], true);
        self::assertIsString($raw, $key . ' is not valid base64');

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function platformVarsDescribeTheDeployment(): void
    {
        $config  = self::project(
            [self::app('app')],
            [self::upstream('https://{default}', 'app:http')],
        );
        $compose = $this->generator->generate(
            $config,
            'main',
            ['default' => ['shop.example.com']],
            platform: self::platform(),
        );

        $env = $this->environment($compose, 'app');

        self::assertSame('1', $env['TRAGWERK']);
        self::assertSame('runtime', $env['TRAGWERK_PHASE']);
        self::assertSame('c0ffee00-1111-2222-3333-444455556666', $env['TRAGWERK_PROJECT_ID']);
        self::assertSame('My Shop', $env['TRAGWERK_PROJECT_NAME']);
        self::assertSame(
            'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            $env['TRAGWERK_PROJECT_ENTROPY'],
        );
        self::assertSame('main', $env['TRAGWERK_ENVIRONMENT']);
        self::assertSame('main', $env['TRAGWERK_BRANCH']);
        self::assertSame('app', $env['TRAGWERK_APPLICATION']);
        self::assertSame('abc1234', $env['TRAGWERK_COMMIT_SHA']);
        self::assertSame('job-1', $env['TRAGWERK_DEPLOY_ID']);
        self::assertSame('https://shop.example.com', $env['TRAGWERK_URL']);
    }

    #[Test]
    public function environmentIsTheSlugOfTheBranchBasename(): void
    {
        $config  = self::project(
            [self::app('app')],
            [self::upstream('https://{default}', 'app:http')],
        );
        $compose = $this->generator->generate(
            $config,
            'feature/New Checkout',
            [],
            platform: self::platform('feature/New Checkout'),
        );

        $env = $this->environment($compose, 'app');

        self::assertSame('new-checkout', $env['TRAGWERK_ENVIRONMENT']);
        self::assertSame('feature/New Checkout', $env['TRAGWERK_BRANCH']);
    }

    #[Test]
    public function workerAndCronInheritPlatformVars(): void
    {
        $app = new ApplicationConfig(
            name: 'app',
            type: ApplicationRuntime::PHP85,
            root: '/',
            web: new WebConfig([new LocationConfig(path: '/', root: 'public', passthru: '/index.php')]),
            workers: [new WorkerDefinitionConfig(name: 'queue', command: 'bin/cli worker:start default')],
            crons: [new CronDefinitionConfig(name: 'nightly', command: 'bin/cli cron', schedule: '0 3 * * *')],
        );

        $compose = $this->generator->generate(
            self::project([$app], [self::upstream('https://{default}', 'app:http')]),
            'main',
            ['default' => ['shop.example.com']],
            platform: self::platform(),
        );

        foreach (['app-worker-queue', 'app-cron'] as $service) {
            $env = $this->environment($compose, $service);
            self::assertSame('1', $env['TRAGWERK'], $service . ' is missing the platform marker');
            self::assertSame('runtime', $env['TRAGWERK_PHASE']);
            self::assertSame('abc1234', $env['TRAGWERK_COMMIT_SHA']);
        }
    }

    #[Test]
    public function routesBlobListsEveryResolvedHost(): void
    {
        $config  = self::project(
            [self::app('app')],
            [self::upstream('https://{default}', 'app:http'), self::upstream('https://www.{default}', 'app:http')],
        );
        $compose = $this->generator->generate(
            $config,
            'main',
            ['default' => ['shop.example.com']],
            platform: self::platform(),
        );

        $routes = self::blob($this->environment($compose, 'app'), 'TRAGWERK_ROUTES');

        self::assertSame([
            [
                'url'         => 'https://shop.example.com',
                'host'        => 'shop.example.com',
                'application' => 'app',
                'tls'         => true,
                'primary'     => true,
            ],
            [
                'url'         => 'https://www.shop.example.com',
                'host'        => 'www.shop.example.com',
                'application' => 'app',
                'tls'         => true,
                'primary'     => false,
            ],
        ], $routes);
    }

    #[Test]
    public function relationshipsBlobKeepsAStableShapePerServiceType(): void
    {
        $config = self::project(
            [
                self::app('app', relationships: [
                    new RelationshipConfig(name: 'database', target: 'db'),
                    new RelationshipConfig(name: 'cache', target: 'redis'),
                ]),
            ],
            [self::upstream('https://{default}', 'app:http')],
            [
                new ServiceConfig(name: 'db', type: ServiceRuntime::POSTGRES18, disk: null),
                new ServiceConfig(name: 'redis', type: ServiceRuntime::REDIS8, disk: null),
            ],
        );

        $compose = $this->generator->generate(
            $config,
            'main',
            ['default' => ['shop.example.com']],
            platform: self::platform(),
        );

        self::assertSame([
            'database' => [
                'type'     => 'postgresql:18',
                'host'     => 'db',
                'port'     => 5432,
                'database' => 'app',
                'username' => 'app',
                'password' => 'secret',
            ],
            'cache' => [
                'type'     => 'redis:8',
                'host'     => 'redis',
                'port'     => 6379,
                'database' => null,
                'username' => null,
                'password' => null,
            ],
        ], self::blob($this->environment($compose, 'app'), 'TRAGWERK_RELATIONSHIPS'));
    }

    #[Test]
    public function appWithoutAResolvableRouteHasAnEmptyUrl(): void
    {
        $config  = self::project([self::app('app')], []);
        $compose = $this->generator->generate($config, 'main', [], platform: self::platform());

        $env = $this->environment($compose, 'app');

        self::assertSame('', $env['TRAGWERK_URL']);
        self::assertSame([], self::blob($env, 'TRAGWERK_ROUTES'));
    }

    #[Test]
    public function userVariablesCannotShadowPlatformVars(): void
    {
        $config  = self::project(
            [self::app('app')],
            [self::upstream('https://{default}', 'app:http')],
        );
        $compose = $this->generator->generate(
            $config,
            'main',
            ['default' => ['shop.example.com']],
            userEnvVars: ['TRAGWERK_PROJECT_ID' => 'spoofed', 'APP_ENV' => 'prod'],
            platform: self::platform(),
        );

        $env = $this->environment($compose, 'app');

        self::assertSame('c0ffee00-1111-2222-3333-444455556666', $env['TRAGWERK_PROJECT_ID']);
        self::assertSame('prod', $env['APP_ENV']);
    }

    #[Test]
    public function withoutAPlatformContextNoPlatformVarsAreEmitted(): void
    {
        $config = self::project(
            [self::app('app')],
            [self::upstream('https://{default}', 'app:http')],
        );

        $env = $this->environment($this->generator->generate($config), 'app');

        foreach (['TRAGWERK', 'TRAGWERK_PROJECT_ID', 'TRAGWERK_ROUTES', 'TRAGWERK_RELATIONSHIPS'] as $key) {
            self::assertArrayNotHasKey($key, $env);
        }
    }

    #[Test]
    public function deployIdIsOmittedDuringTheBuild(): void
    {
        $config  = self::project(
            [self::app('app')],
            [self::upstream('https://{default}', 'app:http')],
        );
        $compose = $this->generator->generate($config, 'main', [], platform: self::platform(deployId: null));

        self::assertArrayNotHasKey('TRAGWERK_DEPLOY_ID', $this->environment($compose, 'app'));
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
