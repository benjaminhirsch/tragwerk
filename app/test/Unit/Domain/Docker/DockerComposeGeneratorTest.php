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
use Tragwerk\Domain\Model\WorkerConfig;
use Tragwerk\Domain\Model\WorkerDefinitionConfig;

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
        self::assertArrayHasKey('start_period', $healthcheck);
        self::assertArrayHasKey('retries', $healthcheck);
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

        self::assertSame('bin/cli worker:start default', $queue['command']);
        self::assertSame('bin/cli metrics:sample', $metrics['command']);
        self::assertSame('unless-stopped', $queue['restart']);
        self::assertSame('unless-stopped', $metrics['restart']);
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
