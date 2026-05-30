<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Docker;

use Tragwerk\Domain\Enum\MountSource;
use Tragwerk\Domain\Enum\RouteType;
use Tragwerk\Domain\Enum\ServiceRuntime;
use Tragwerk\Domain\Model\ApplicationConfig;
use Tragwerk\Domain\Model\ProjectConfig;
use Tragwerk\Domain\Model\RouteConfig;
use Tragwerk\Domain\Model\ServiceConfig;

use function array_key_exists;
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
     *
     * @return array<string, mixed>
     */
    public function generate(ProjectConfig $config, string $branch = '', array $domainsByPlaceholder = []): array
    {
        /** @var array<string, mixed> $services */
        $services = [];
        /** @var array<string, null> $volumes */
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
                if ($mount->source !== MountSource::LOCAL) {
                    continue;
                }

                $volName           = $appSlug . '-' . $this->slugify($mount->name);
                $appVolumes[]      = $volName . ':/app/' . ltrim($mount->path, '/');
                $volumes[$volName] = null;
            }

            /** @var array<string, array<string, string>> $dependsOn */
            $dependsOn   = [];
            $environment = ['SERVER_NAME' => ':80'];

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

            $labels = $this->buildTraefikLabels($app, $config, $domainsByPlaceholder, $branchSlug);

            /** @var array<string, mixed> $svcConfig */
            $svcConfig = [
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

            if ($labels !== []) {
                $svcConfig['labels'] = $labels;
            }

            if ($dependsOn !== []) {
                $svcConfig['depends_on'] = $dependsOn;
            }

            // App containers join the shared Traefik network so the server-level Traefik can route to them
            $svcConfig['networks'] = ['default', 'tragwerk-net'];

            $services[$appSlug] = $svcConfig;
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

            $services[$slug] = $svcConfig;
        }

        return [
            'services' => $services,
            'volumes'  => $volumes,
            'networks' => ['tragwerk-net' => ['external' => true]],
        ];
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
    ): array {
        $slug     = $this->slugify($app->name);
        $fullSlug = $branchSlug !== '' ? $slug . '-' . $branchSlug : $slug;
        $routes   = $this->routesForApp($app, $config);

        if ($routes === []) {
            return [];
        }

        $labels      = ['traefik.enable=true'];
        $labels[]    = 'traefik.http.services.' . $fullSlug . '.loadbalancer.server.port=80';
        $hasRedirect = false;
        $upIdx       = 0;
        $redIdx      = 0;

        foreach ($routes as $route) {
            $isHttps     = $this->patternIsHttps($route->pattern);
            $placeholder = $this->extractPlaceholder($route->pattern);
            $resolved    = $placeholder !== null ? ($domainsByPlaceholder[$placeholder] ?? []) : [];
            $hosts       = $resolved !== [] ? $resolved : [$this->extractHost($route->pattern)];

            foreach ($hosts as $host) {
                if ($route->type === RouteType::REDIRECT) {
                    $hasRedirect = true;
                    $routerName  = $fullSlug . '-http-' . $redIdx;
                    $entrypoint  = $isHttps ? 'websecure' : 'web';
                    $middleware  = $fullSlug . '-redirect-to-https';
                    $labels[]    = 'traefik.http.routers.' . $routerName . '.rule=Host(`' . $host . '`)';
                    $labels[]    = 'traefik.http.routers.' . $routerName . '.entrypoints=' . $entrypoint;
                    $labels[]    = 'traefik.http.routers.' . $routerName . '.middlewares=' . $middleware;
                    $redIdx++;
                } else {
                    $routerName = $fullSlug . '-https-' . $upIdx;
                    $entrypoint = $isHttps ? 'websecure' : 'web';
                    $labels[]   = 'traefik.http.routers.' . $routerName . '.rule=Host(`' . $host . '`)';
                    $labels[]   = 'traefik.http.routers.' . $routerName . '.entrypoints=' . $entrypoint;
                    $labels[]   = 'traefik.http.routers.' . $routerName . '.service=' . $fullSlug;
                    if ($isHttps) {
                        $labels[] = 'traefik.http.routers.' . $routerName . '.tls.certresolver=letsencrypt';
                    }

                    $upIdx++;
                }
            }
        }

        if ($hasRedirect) {
            $labels[] = 'traefik.http.middlewares.' . $fullSlug . '-redirect-to-https.redirectscheme.scheme=https';
            $labels[] = 'traefik.http.middlewares.' . $fullSlug . '-redirect-to-https.redirectscheme.permanent=true';
        }

        return $labels;
    }

    /** @return list<RouteConfig> */
    private function routesForApp(ApplicationConfig $app, ProjectConfig $config): array
    {
        $slug = $this->slugify($app->name);

        /** @var array<string, RouteConfig> $redirectsByDomain */
        $redirectsByDomain = [];
        foreach ($config->routes as $route) {
            if ($route->type !== RouteType::REDIRECT) {
                continue;
            }

            $redirectsByDomain[$this->extractHost($route->pattern)] = $route;
        }

        $firstApp = $config->applications[0] ?? $app;

        $appRoutes = [];
        foreach ($config->routes as $route) {
            if ($route->type !== RouteType::UPSTREAM) {
                continue;
            }

            $parts        = explode(':', $route->upstream ?? '', 2);
            $upstreamName = $parts[0];

            $matchedByName = $upstreamName === $app->name || $upstreamName === $slug;
            $noAppMatches  = ! $this->anyAppMatches($upstreamName, $config);
            $matches       = $matchedByName || ($noAppMatches && $app === $firstApp);

            if (! $matches) {
                continue;
            }

            $appRoutes[] = $route;

            $host = $this->extractHost($route->pattern);
            if (! array_key_exists($host, $redirectsByDomain)) {
                continue;
            }

            $appRoutes[] = $redirectsByDomain[$host];
        }

        return $appRoutes;
    }

    private function anyAppMatches(string $upstreamName, ProjectConfig $config): bool
    {
        foreach ($config->applications as $candidate) {
            if ($upstreamName === $candidate->name || $upstreamName === $this->slugify($candidate->name)) {
                return true;
            }
        }

        return false;
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
        if (str_starts_with($service->type->value, 'postgresql:')) {
            return [
                'HOST'     => $slug,
                'PORT'     => '5432',
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
                'PORT'     => '3306',
                'DATABASE' => 'app',
                'USER'     => 'app',
                'PASSWORD' => 'secret',
            ];
        }

        return [
            'HOST' => $slug,
            'PORT' => '6379',
        ];
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
