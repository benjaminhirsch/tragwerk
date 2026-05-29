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
use function preg_replace;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function strtoupper;

final readonly class DockerComposeGenerator
{
    public function __construct(private ServiceImageResolver $imageResolver)
    {
    }

    /** @return array<string, mixed> */
    public function generate(ProjectConfig $config): array
    {
        /** @var array<string, mixed> $services */
        $services = [];
        /** @var array<string, null> $volumes */
        $volumes = [];

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

            $labels = $this->buildTraefikLabels($app, $config);

            /** @var array<string, mixed> $svcConfig */
            $svcConfig = [
                'build'       => ['context' => '.', 'dockerfile' => 'Dockerfile.' . $appSlug],
                'read_only'   => true,
                'environment' => $environment,
            ];

            if (str_starts_with($app->type->value, 'php:')) {
                // PHP and Caddy need writable scratch space even in a read-only container
                $svcConfig['tmpfs'] = ['/tmp', '/data', '/config'];
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

            $services[$appSlug] = $svcConfig;
        }

        $services['traefik'] = $this->buildTraefikService();

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

            $svcConfig['healthcheck'] = $this->serviceHealthcheck($service->type);

            $services[$slug] = $svcConfig;
        }

        $volumes['traefik-certs'] = null;

        return ['services' => $services, 'volumes' => $volumes];
    }

    public function slugify(string $name): string
    {
        $slug = preg_replace('/[\s_]+/', '-', strtolower($name)) ?? '';

        return preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';
    }

    /** @return list<string> */
    private function buildTraefikLabels(ApplicationConfig $app, ProjectConfig $config): array
    {
        $slug   = $this->slugify($app->name);
        $routes = $this->routesForApp($app, $config);

        if ($routes === []) {
            return [];
        }

        $labels      = ['traefik.enable=true'];
        $labels[]    = 'traefik.http.services.' . $slug . '.loadbalancer.server.port=80';
        $hasRedirect = false;
        $upIdx       = 0;
        $redIdx      = 0;

        foreach ($routes as $route) {
            $host    = $this->extractHost($route->pattern);
            $isHttps = $this->patternIsHttps($route->pattern);

            if ($route->type === RouteType::REDIRECT) {
                $hasRedirect = true;
                $routerName  = $slug . '-http-' . $redIdx;
                $entrypoint  = $isHttps ? 'websecure' : 'web';
                $labels[]    = 'traefik.http.routers.' . $routerName . '.rule=Host(`' . $host . '`)';
                $labels[]    = 'traefik.http.routers.' . $routerName . '.entrypoints=' . $entrypoint;
                $labels[]    = 'traefik.http.routers.' . $routerName . '.middlewares=' . $slug . '-redirect-to-https';
                $redIdx++;
            } else {
                $routerName = $slug . '-https-' . $upIdx;
                $entrypoint = $isHttps ? 'websecure' : 'web';
                $labels[]   = 'traefik.http.routers.' . $routerName . '.rule=Host(`' . $host . '`)';
                $labels[]   = 'traefik.http.routers.' . $routerName . '.entrypoints=' . $entrypoint;
                $labels[]   = 'traefik.http.routers.' . $routerName . '.service=' . $slug;
                if ($isHttps) {
                    $labels[] = 'traefik.http.routers.' . $routerName . '.tls.certresolver=letsencrypt';
                }

                $upIdx++;
            }
        }

        if ($hasRedirect) {
            $labels[] = 'traefik.http.middlewares.' . $slug . '-redirect-to-https.redirectscheme.scheme=https';
            $labels[] = 'traefik.http.middlewares.' . $slug . '-redirect-to-https.redirectscheme.permanent=true';
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

        return str_replace('{default}', '${DOMAIN:-localhost}', $parts[0]);
    }

    private function patternIsHttps(string $pattern): bool
    {
        return str_starts_with($pattern, 'https://');
    }

    /** @return array<string, mixed> */
    private function buildTraefikService(): array
    {
        return [
            'image'   => 'traefik:v3',
            'command' => [
                '--providers.docker=true',
                '--providers.docker.exposedbydefault=false',
                '--entrypoints.web.address=:80',
                '--entrypoints.websecure.address=:443',
                '--certificatesresolvers.letsencrypt.acme.tlschallenge=true',
                '--certificatesresolvers.letsencrypt.acme.email=${ACME_EMAIL:-admin@example.com}',
                '--certificatesresolvers.letsencrypt.acme.storage=/certs/acme.json',
            ],
            'ports'   => ['80:80', '443:443'],
            'volumes' => [
                '/var/run/docker.sock:/var/run/docker.sock:ro',
                'traefik-certs:/certs',
            ],
        ];
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
            return '/var/lib/postgresql/data';
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
}
