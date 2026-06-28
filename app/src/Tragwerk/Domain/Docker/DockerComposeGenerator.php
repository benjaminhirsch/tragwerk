<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Docker;

use Tragwerk\Domain\Enum\ServiceRuntime;
use Tragwerk\Domain\Model\ApplicationConfig;
use Tragwerk\Domain\Model\ProjectConfig;
use Tragwerk\Domain\Model\RouteConfig;
use Tragwerk\Domain\Model\ServiceConfig;
use Tragwerk\Domain\Model\WorkerDefinitionConfig;

use function array_any;
use function array_key_exists;
use function array_map;
use function explode;
use function ltrim;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function strtoupper;

final readonly class DockerComposeGenerator
{
    public function __construct(private ServiceImageResolver $imageResolver)
    {
    }

    /**
     * @param array<string, list<string>> $domainsByPlaceholder
     * @param array<string, string>       $imageTags            Map of appSlug → fully-qualified image tag.
     *                                                          When provided the service uses `image:` instead
     *                                                          of `build:`.
     * @param array<string, string>       $userEnvVars          User-defined env vars injected into app containers.
     *                                                          System keys (SERVER_NAME, TRAGWERK_*) take precedence.
     *
     * @return array<string, mixed>
     */
    public function generate(
        ProjectConfig $config,
        string $branch = '',
        array $domainsByPlaceholder = [],
        array $imageTags = [],
        string $projectSlug = '',
        array $userEnvVars = [],
    ): array {
        /** @var array<string, mixed> $services */
        $services = [];
        /** @var array<string, mixed> $volumes */
        $volumes = [];

        $branchSlug = $this->slugify($branch);

        /** @var array<string, ServiceConfig> $serviceIndex */
        $serviceIndex = [];
        foreach ($config->services as $service) {
            $serviceIndex[$service->name] = $service;
        }

        foreach ($config->applications as $app) {
            $appSlug    = $this->slugify($app->name);
            $appVolumes = [];

            foreach ($app->mounts as $mount) {
                $mountSlug         = $this->slugify($mount->name);
                $volName           = $appSlug . '-' . $mountSlug;
                $appVolumes[]      = $volName . ':/app/' . ltrim($mount->path, '/');
                $volumes[$volName] = null;
            }

            /** @var array<string, array<string, string>> $dependsOn */
            $dependsOn                  = [];
            $environment                = $userEnvVars;
            $environment['SERVER_NAME'] = ':80';

            // Worker scripts honour MAX_REQUESTS to restart after N requests (memory-leak guard).
            if ($app->workerMode !== null && $app->workerMode->maxRequests > 0) {
                $environment['MAX_REQUESTS'] = (string) $app->workerMode->maxRequests;
            }

            foreach ($app->relationships as $rel) {
                $targetSlug             = $this->slugify($rel->target);
                $dependsOn[$targetSlug] = ['condition' => 'service_healthy'];

                if (! array_key_exists($rel->target, $serviceIndex)) {
                    continue;
                }

                $prefix = 'TRAGWERK_' . strtoupper(str_replace(['-', ' '], '_', $rel->name)) . '_';
                foreach ($this->connectionVars($serviceIndex[$rel->target], $targetSlug) as $key => $value) {
                    $environment[$prefix . $key] = $value;
                }
            }

            $labels = $this->buildTraefikLabels($app, $config, $domainsByPlaceholder, $branchSlug, $projectSlug);

            $svcConfig = isset($imageTags[$appSlug])
                ? ['image' => $imageTags[$appSlug], 'read_only' => true, 'environment' => $environment]
                : [
                    'build'       => ['context' => '.', 'dockerfile' => 'Dockerfile.' . $appSlug],
                    'read_only'   => true,
                    'environment' => $environment,
                ];

            if (str_starts_with($app->type->value, 'php:')) {
                // PHP and Caddy need writable scratch space even in a read-only container
                $svcConfig['tmpfs']       = ['/tmp', '/data', '/config'];
                $svcConfig['healthcheck'] = [
                    'test'         => $this->phpHealthcheck(),
                    'interval'     => '5s',
                    'timeout'      => '6s',
                    'retries'      => 12,
                    'start_period' => '30s',
                ];
            }

            if ($appVolumes !== []) {
                $svcConfig['volumes'] = $appVolumes;
            }

            if ($dependsOn !== []) {
                $svcConfig['depends_on'] = $dependsOn;
            }

            // App containers join the shared Traefik network so the server-level Traefik can route to them
            $svcConfig['networks'] = ['default', 'tragwerk-net'];

            if ($labels !== []) {
                $svcConfig['labels'] = $labels;
            }

            $services[$appSlug] = $svcConfig;

            foreach ($app->workers as $workerDef) {
                $services[$appSlug . '-worker-' . $this->slugify($workerDef->name)] =
                    $this->buildWorkerService($svcConfig, $workerDef);
            }

            if ($app->crons === []) {
                continue;
            }

            $services[$appSlug . '-cron'] = $this->buildCronService($svcConfig);
        }

        foreach ($config->services as $service) {
            $slug = $this->slugify($service->name);

            /** @var array<string, mixed> $svcConfig */
            $svcConfig = ['image' => $this->imageResolver->forService($service->type)];

            $env = $this->serviceEnvironment($service->type);
            if ($env !== []) {
                $svcConfig['environment'] = $env;
            }

            $dataPath = $this->dataVolumePath($service->type);
            if ($dataPath !== null) {
                $volName              = $slug . '-data';
                $svcConfig['volumes'] = [$volName . ':' . $dataPath];
                $volumes[$volName]    = null;
            }

            $svcConfig['healthcheck']       = $this->serviceHealthcheck($service->type);
            $svcConfig['stop_grace_period'] = $this->serviceStopGracePeriod($service->type);

            if ($service->localPort !== null) {
                // main/master keeps the configured, predictable host port; every other
                // branch lets Docker assign a free loopback port (find it via `docker ps`)
                // so two branches of the same project never collide on the host. Always
                // hard-bound to 127.0.0.1 — a user can never coerce a public 0.0.0.0 bind.
                $isRootBranch = $branch === 'main' || $branch === 'master';
                $hostPort     = $isRootBranch ? (string) $service->localPort : '';

                $svcConfig['ports'] = [
                    '127.0.0.1:' . $hostPort . ':' . $this->servicePort($service->type),
                ];
            }

            $services[$slug] = $svcConfig;
        }

        return [
            'services' => $services,
            'volumes'  => $volumes,
            'networks' => ['tragwerk-net' => ['external' => true]],
        ];
    }

    /**
     * @param array<string, mixed> $appService
     *
     * @return array<string, mixed>
     */
    private function buildWorkerService(array $appService, WorkerDefinitionConfig $def): array
    {
        $worker = [];

        if (isset($appService['image'])) {
            $worker['image'] = $appService['image'];
        } elseif (isset($appService['build'])) {
            $worker['build'] = $appService['build'];
        }

        $worker['command'] = $def->command;
        $worker['restart'] = 'unless-stopped';

        if (isset($appService['environment'])) {
            $worker['environment'] = $appService['environment'];
        }

        if (isset($appService['volumes'])) {
            $worker['volumes'] = $appService['volumes'];
        }

        $worker['read_only']   = true;
        $worker['healthcheck'] = ['disable' => true];

        if (isset($appService['tmpfs'])) {
            $worker['tmpfs'] = $appService['tmpfs'];
        }

        if (isset($appService['depends_on'])) {
            $worker['depends_on'] = $appService['depends_on'];
        }

        $worker['networks'] = $appService['networks'];

        return $worker;
    }

    /**
     * Cron sidecar: one container per application running supercronic over the
     * crontab baked into the image (see DockerfileGenerator). Shares the app
     * image, environment and mounts like a worker, but is scheduler-driven.
     *
     * @param array<string, mixed> $appService
     *
     * @return array<string, mixed>
     */
    private function buildCronService(array $appService): array
    {
        $cron = [];

        if (isset($appService['image'])) {
            $cron['image'] = $appService['image'];
        } elseif (isset($appService['build'])) {
            $cron['build'] = $appService['build'];
        }

        // -json: machine-parseable logs (consumed by the live log viewer and the cron:sample ticker).
        // -no-reap: supercronic's PID 1 process reaper fork-execs a helper that dies with ENOENT in
        // this read-only image, crash-looping the container before any job runs. Our jobs are simple
        // one-shot `bin/cli` commands (sh → php → exit) that leave no orphaned children to reap, so
        // disabling the reaper is safe.
        $cron['command'] = 'supercronic -no-reap -json /etc/supercronic/crontab';
        $cron['restart'] = 'unless-stopped';

        if (isset($appService['environment'])) {
            $cron['environment'] = $appService['environment'];
        }

        if (isset($appService['volumes'])) {
            $cron['volumes'] = $appService['volumes'];
        }

        $cron['read_only'] = true;
        // supercronic -test validates the crontab is present and parseable — a meaningful liveness
        // signal (a process check would be redundant since supercronic is PID 1 in this container).
        $cron['healthcheck'] = [
            'test'         => ['CMD', 'supercronic', '-test', '/etc/supercronic/crontab'],
            'interval'     => '30s',
            'timeout'      => '10s',
            'retries'      => 3,
            'start_period' => '5s',
        ];

        if (isset($appService['tmpfs'])) {
            $cron['tmpfs'] = $appService['tmpfs'];
        }

        if (isset($appService['depends_on'])) {
            $cron['depends_on'] = $appService['depends_on'];
        }

        $cron['networks'] = $appService['networks'];

        return $cron;
    }

    public function slugify(string $name): string
    {
        $slug = preg_replace('/[\s_]+/', '-', strtolower($name)) ?? '';

        return preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';
    }

    /**
     * @param array<string, list<string>> $domainsByPlaceholder
     *
     * @return list<string>
     */
    private function buildTraefikLabels(
        ApplicationConfig $app,
        ProjectConfig $config,
        array $domainsByPlaceholder,
        string $branchSlug,
        string $projectSlug = '',
    ): array {
        $slug     = $this->slugify($app->name);
        $suffix   = $projectSlug !== '' && $branchSlug !== ''
            ? $projectSlug . '-' . $branchSlug
            : ($projectSlug !== '' ? $projectSlug : $branchSlug);
        $fullSlug = $suffix !== '' ? $slug . '-' . $suffix : $slug;
        $routes   = $this->routesForApp($app, $config);

        if ($routes === []) {
            return [];
        }

        $labels   = ['traefik.enable=true'];
        $labels[] = 'traefik.http.services.' . $fullSlug . '.loadbalancer.server.port=80';
        $upIdx    = 0;

        foreach ($routes as $route) {
            $isHttps     = $this->patternIsHttps($route->pattern);
            $placeholder = $this->extractPlaceholder($route->pattern);
            $resolved    = $placeholder !== null ? ($domainsByPlaceholder[$placeholder] ?? []) : [];
            $hosts       = $resolved !== [] ? $resolved : [$this->extractHost($route->pattern)];

            // Substitute the placeholder inside the full host part (e.g. "www.{default}" → "www.example.com")
            if ($resolved !== [] && $placeholder !== null) {
                $rawHost = $this->extractRawHostPart($route->pattern);
                $hosts   = array_map(
                    static fn (string $d): string => (string) str_replace('{' . $placeholder . '}', $d, $rawHost),
                    $resolved,
                );
            }

            foreach ($hosts as $host) {
                $routerName = $fullSlug . '-https-' . $upIdx;
                $entrypoint = $isHttps ? 'websecure' : 'web';

                $labels[] = 'traefik.http.routers.' . $routerName . '.rule=Host(`' . $host . '`)';
                $labels[] = 'traefik.http.routers.' . $routerName . '.entrypoints=' . $entrypoint;
                $labels[] = 'traefik.http.routers.' . $routerName . '.service=' . $fullSlug;

                if ($isHttps) {
                    $labels[] = 'traefik.http.routers.' . $routerName . '.tls.certresolver=letsencrypt';
                }

                $upIdx++;
            }
        }

        return $labels;
    }

    /** @return list<RouteConfig> */
    private function routesForApp(ApplicationConfig $app, ProjectConfig $config): array
    {
        $slug     = $this->slugify($app->name);
        $firstApp = $config->applications[0] ?? $app;
        $routes   = [];

        foreach ($config->routes as $route) {
            $upstreamName  = explode(':', $route->upstream ?? '', 2)[0];
            $matchedByName = $upstreamName === $app->name || $upstreamName === $slug;
            $noAppMatches  = ! $this->anyAppMatches($upstreamName, $config);

            if (! $matchedByName && ! ($noAppMatches && $app === $firstApp)) {
                continue;
            }

            $routes[] = $route;
        }

        return $routes;
    }

    private function anyAppMatches(string $upstreamName, ProjectConfig $config): bool
    {
        return array_any(
            $config->applications,
            fn ($candidate) => $upstreamName === $candidate->name || $upstreamName === $this->slugify($candidate->name),
        );
    }

    private function extractHost(string $pattern): string
    {
        $withoutScheme = preg_replace('#^https?://#', '', $pattern) ?? $pattern;
        $parts         = explode('/', $withoutScheme, 2);

        return preg_replace_callback(
            '/\{([^}]+)\}/',
            static fn (array $m) => '${' . strtoupper($m[1]) . ':-localhost}',
            $parts[0],
        ) ?? $parts[0];
    }

    private function extractPlaceholder(string $pattern): string|null
    {
        return preg_match('/\{([^}]+)\}/', $pattern, $m) === 1 ? $m[1] : null;
    }

    private function patternIsHttps(string $pattern): bool
    {
        return str_starts_with($pattern, 'https://');
    }

    /** @return array<string, string> */
    private function connectionVars(ServiceConfig $service, string $slug): array
    {
        $port = (string) $this->servicePort($service->type);

        if (str_starts_with($service->type->value, 'postgresql:')) {
            return [
                'HOST'     => $slug,
                'PORT'     => $port,
                'DATABASE' => 'app',
                'USER'     => 'app',
                'PASSWORD' => 'secret',
            ];
        }

        if (
            str_starts_with($service->type->value, 'mysql:')
            || str_starts_with($service->type->value, 'mariadb:')
        ) {
            return [
                'HOST'     => $slug,
                'PORT'     => $port,
                'DATABASE' => 'app',
                'USER'     => 'app',
                'PASSWORD' => 'secret',
            ];
        }

        return [
            'HOST' => $slug,
            'PORT' => $port,
        ];
    }

    /** Internal container port a service listens on, keyed by runtime family. */
    private function servicePort(ServiceRuntime $runtime): int
    {
        if (str_starts_with($runtime->value, 'postgresql:')) {
            return 5432;
        }

        if (
            str_starts_with($runtime->value, 'mysql:')
            || str_starts_with($runtime->value, 'mariadb:')
        ) {
            return 3306;
        }

        return 6379;
    }

    /** @return array<string, string> */
    private function serviceEnvironment(ServiceRuntime $runtime): array
    {
        if (str_starts_with($runtime->value, 'postgresql:')) {
            return [
                'POSTGRES_DB'       => 'app',
                'POSTGRES_USER'     => 'app',
                'POSTGRES_PASSWORD' => 'secret',
            ];
        }

        if (
            str_starts_with($runtime->value, 'mysql:')
            || str_starts_with($runtime->value, 'mariadb:')
        ) {
            return [
                'MYSQL_DATABASE'      => 'app',
                'MYSQL_USER'          => 'app',
                'MYSQL_PASSWORD'      => 'secret',
                'MYSQL_ROOT_PASSWORD' => 'root',
            ];
        }

        return [];
    }

    private function dataVolumePath(ServiceRuntime $runtime): string|null
    {
        if (str_starts_with($runtime->value, 'postgresql:')) {
            // PostgreSQL 18+ stores data in a version-specific subdirectory under /var/lib/postgresql
            // and refuses to start when a volume is mounted directly at /data.
            $version = (int) explode(':', $runtime->value, 2)[1];

            return $version >= 18 ? '/var/lib/postgresql' : '/var/lib/postgresql/data';
        }

        if (
            str_starts_with($runtime->value, 'mysql:')
            || str_starts_with($runtime->value, 'mariadb:')
        ) {
            return '/var/lib/mysql';
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function serviceHealthcheck(ServiceRuntime $runtime): array
    {
        if (str_starts_with($runtime->value, 'postgresql:')) {
            return [
                'test'         => ['CMD-SHELL', 'pg_isready -U app -d app'],
                'interval'     => '5s',
                'timeout'      => '3s',
                'retries'      => 10,
                'start_period' => '10s',
            ];
        }

        if (
            str_starts_with($runtime->value, 'mysql:')
            || str_starts_with($runtime->value, 'mariadb:')
        ) {
            return [
                'test'         => ['CMD', 'mysqladmin', 'ping', '-h', 'localhost', '-u', 'app', '--password=secret'],
                'interval'     => '5s',
                'timeout'      => '3s',
                'retries'      => 10,
                'start_period' => '10s',
            ];
        }

        // Redis / Valkey
        return [
            'test'     => ['CMD', 'redis-cli', 'ping'],
            'interval' => '5s',
            'timeout'  => '3s',
            'retries'  => 5,
        ];
    }

    private function serviceStopGracePeriod(ServiceRuntime $runtime): string
    {
        if (
            str_starts_with($runtime->value, 'mysql:')
            || str_starts_with($runtime->value, 'mariadb:')
        ) {
            return '60s';
        }

        return '30s';
    }

    private function extractRawHostPart(string $pattern): string
    {
        $withoutScheme = preg_replace('#^https?://#', '', $pattern) ?? $pattern;

        return explode('/', $withoutScheme, 2)[0];
    }

    /** @return list<string> */
    private function phpHealthcheck(): array
    {
        // Uses PHP's built-in TCP client — works on Alpine and Debian regardless of whether
        // the curl or wget binaries are installed.
        // $$ is used instead of $ so Docker Compose does not interpolate $f/$e/$m as
        // compose variables when it reads the generated docker-compose.yml.
        $script = '$$f=@stream_socket_client("tcp://127.0.0.1:80",$$e,$$m,5);exit($$f===false?1:0);';

        return ['CMD-SHELL', 'php -r \'' . $script . '\''];
    }
}
