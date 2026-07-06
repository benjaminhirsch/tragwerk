<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Docker;

use InvalidArgumentException;
use Tragwerk\Domain\Enum\HookType;
use Tragwerk\Domain\Model\ApplicationConfig;
use Tragwerk\Domain\Model\ExtensionConfig;
use Tragwerk\Domain\Model\HookConfig;
use Tragwerk\Domain\Model\LocationConfig;
use Tragwerk\Domain\Model\PhpConfig;

use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function implode;
use function ltrim;
use function preg_replace;
use function rtrim;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function trim;

final readonly class DockerfileGenerator
{
    public function __construct(private ServiceImageResolver $imageResolver)
    {
    }

    public function generate(ApplicationConfig $app): DockerfileOutput
    {
        foreach ($app->extensions as $ext) {
            if ($ext->name === 'swoole') {
                throw new InvalidArgumentException(
                    'The swoole extension is not supported with FrankenPHP. Use the FrankenPHP worker mode instead.',
                );
            }
        }

        $slug            = $this->slugify($app->name);
        $buildHooks      = $this->filterHooks($app->hooks, HookType::BUILD);
        $deployHooks     = $this->filterHooks($app->hooks, HookType::DEPLOY);
        $postDeployHooks = $this->filterHooks($app->hooks, HookType::POST_DEPLOY);
        $hasEntrypoint   = $deployHooks !== [] || $postDeployHooks !== [];
        $isPhp           = str_starts_with($app->type->value, 'php:');
        $hasCaddyfile    = $isPhp && $app->web->locations !== [];
        $hasCrons        = $app->crons !== [];
        $hasPhpIni       = $isPhp && $app->php !== null && $app->php->settings !== [];

        return new DockerfileOutput(
            dockerfileName:    'Dockerfile.' . $slug,
            dockerfileContent: $this->buildDockerfile(
                $app,
                $slug,
                $buildHooks,
                $hasEntrypoint,
                $hasCaddyfile,
                $hasCrons,
                $hasPhpIni,
            ),
            entrypointName:    $hasEntrypoint ? 'docker-entrypoint.' . $slug . '.sh' : null,
            entrypointContent: $hasEntrypoint ? $this->buildEntrypoint($deployHooks, $postDeployHooks) : null,
            caddyfileName:     $hasCaddyfile ? 'Caddyfile.' . $slug : null,
            caddyfileContent:  $hasCaddyfile ? $this->buildCaddyfile($app) : null,
            crontabName:       $hasCrons ? 'crontab.' . $slug : null,
            crontabContent:    $hasCrons ? $this->buildCrontab($app) : null,
            phpIniName:        $hasPhpIni ? 'php.' . $slug . '.ini' : null,
            phpIniContent:     $hasPhpIni && $app->php !== null ? $this->buildPhpIni($app->php) : null,
        );
    }

    public function slugify(string $name): string
    {
        $slug = preg_replace('/[\s_]+/', '-', strtolower($name)) ?? '';

        return preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';
    }

    /**
     * @param list<HookConfig> $hooks
     *
     * @return list<HookConfig>
     */
    private function filterHooks(array $hooks, HookType $type): array
    {
        return array_values(array_filter($hooks, static fn (HookConfig $h) => $h->type === $type));
    }

    /** @param list<HookConfig> $buildHooks */
    private function buildDockerfile(
        ApplicationConfig $app,
        string $slug,
        array $buildHooks,
        bool $hasEntrypoint,
        bool $hasCaddyfile,
        bool $hasCrons,
        bool $hasPhpIni,
    ): string {
        $image      = $this->imageResolver->forApplication($app->type);
        $copySource = $app->root === '/' ? '.' : ltrim($app->root, '/');

        $lines   = [];
        $lines[] = 'FROM ' . $image;
        $lines[] = '';
        $lines[] = 'WORKDIR /app';

        $isPhp = str_starts_with($app->type->value, 'php:');

        if ($isPhp) {
            $lines[] = '';
            $lines[] = $this->buildExtensionRun($app->extensions);
            $lines[] = '';
            $lines[] = 'COPY --from=composer:latest /usr/bin/composer /usr/bin/composer';
            $lines[] = '';

            if ($hasPhpIni) {
                // conf.d is scanned alphabetically; the "zz-" prefix loads this last
                // so user directives override the base image / app defaults. Copied
                // before the source and build hooks so build-time PHP (composer,
                // artisan) already runs under these settings.
                $lines[] = 'COPY php.' . $slug . '.ini /usr/local/etc/php/conf.d/zz-tragwerk.ini';
                $lines[] = '';
            }
        }

        if ($hasCrons) {
            $lines[] = $this->buildSupercronicRun();
            $lines[] = '';
        }

        $lines[] = 'COPY ' . $copySource . ' .';

        if ($hasCrons) {
            $lines[] = '';
            $lines[] = 'COPY crontab.' . $slug . ' /etc/supercronic/crontab';
        }

        foreach ($buildHooks as $hook) {
            $scriptLines = $this->parseScriptLines($hook->value);
            if ($scriptLines === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = 'RUN ' . implode(" \\\n    && ", $scriptLines);
        }

        if ($hasCaddyfile) {
            $lines[] = '';
            $lines[] = 'COPY Caddyfile.' . $slug . ' /etc/frankenphp/Caddyfile';
        }

        if ($hasEntrypoint) {
            $lines[] = '';
            $lines[] = 'COPY docker-entrypoint.' . $slug . '.sh /usr/local/bin/docker-entrypoint.sh';
            $lines[] = 'RUN chmod +x /usr/local/bin/docker-entrypoint.sh';
            $lines[] = '';
            $lines[] = 'ENTRYPOINT ["docker-entrypoint.sh"]';

            if (str_starts_with($app->type->value, 'php:')) {
                $lines[] = 'CMD ["frankenphp", "run",'
                    . ' "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]';
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<HookConfig> $deployHooks
     * @param list<HookConfig> $postDeployHooks
     */
    private function buildEntrypoint(array $deployHooks, array $postDeployHooks): string
    {
        $lines   = [];
        $lines[] = '#!/bin/sh';
        $lines[] = 'set -e';
        $lines[] = '';

        foreach ($deployHooks as $hook) {
            foreach ($this->parseScriptLines($hook->value) as $line) {
                $lines[] = $line;
            }

            $lines[] = '';
        }

        if ($postDeployHooks !== []) {
            $lines[] = '(';
            foreach ($postDeployHooks as $hook) {
                foreach ($this->parseScriptLines($hook->value) as $line) {
                    $lines[] = '    ' . $line;
                }
            }

            $lines[] = ') &';
            $lines[] = '';
        }

        $lines[] = 'exec "$@"';

        return implode("\n", $lines) . "\n";
    }

    private function buildCaddyfile(ApplicationConfig $app): string
    {
        $lines   = [];
        $lines[] = '{';

        if ($app->workerMode !== null) {
            $lines[] = '    frankenphp {';
            $lines[] = '        worker {';
            $lines[] = '            file ' . $this->workerFile($app);

            if ($app->workerMode->count !== null) {
                $lines[] = '            num ' . $app->workerMode->count;
            }

            $lines[] = '        }';
            $lines[] = '    }';
        } else {
            $lines[] = '    frankenphp';
        }

        $lines[] = '    order php_server before file_server';
        // Enable Caddy/FrankenPHP Prometheus metrics on the container-internal admin endpoint
        // (localhost:2019/metrics) — scraped per-environment by Tragwerk, no external exposure.
        $lines[] = '    servers {';
        $lines[] = '        metrics';
        $lines[] = '    }';
        $lines[] = '}';

        foreach ($app->web->locations as $location) {
            $root = '/app/' . ltrim($location->root, '/');

            $lines[] = '';
            $lines[] = count($app->web->locations) > 1
                ? ':80' . rtrim($location->path, '/')
                : ':80';

            $lines[] = '{';
            $lines[] = '    root * ' . $root;
            $lines[] = '    encode zstd br gzip';

            if ($location->passthru !== null && $location->passthru !== 'none') {
                $lines[] = '    php_server';
            } else {
                // Serve the extensionless path, then "{path}.html", then a directory
                // index. This lets pre-rendered static sites with clean URLs (e.g.
                // VitePress, Astro, Hugo) resolve "/foo" to "foo.html" on direct
                // load; without it Caddy only finds the file when an extension is given.
                $lines[] = '    try_files {path} {path}.html {path}/index.html';
                $lines[] = '    file_server';
            }

            $lines[] = '}';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Resolves the worker front controller from the single passthru location.
     * Worker mode is meaningless without a PHP entry point, and ambiguous with
     * more than one, so exactly one passthru location is required.
     */
    private function workerFile(ApplicationConfig $app): string
    {
        $passthruLocations = array_values(array_filter(
            $app->web->locations,
            static fn (LocationConfig $loc): bool => $loc->passthru !== null && $loc->passthru !== 'none',
        ));

        if (count($passthruLocations) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Worker mode for application "%s" requires exactly one <location> with a '
                . 'passthru front controller, found %d.',
                $app->name,
                count($passthruLocations),
            ));
        }

        $location = $passthruLocations[0];

        return '/app/' . ltrim($location->root, '/') . '/' . ltrim((string) $location->passthru, '/');
    }

    /**
     * Installs supercronic, a lightweight crontab runner built for containers:
     * it runs as the unprivileged container user, logs jobs to stdout/stderr
     * (so `docker logs` works) and needs no system cron daemon. The binary is
     * selected per build architecture (amd64/arm64 — matching the rest of the
     * multi-arch stack); version + per-arch SHA1 are pinned for reproducible,
     * integrity-checked builds (bump both together).
     */
    private function buildSupercronicRun(): string
    {
        $version  = 'v0.2.33';
        $base     = 'https://github.com/aptible/supercronic/releases/download/' . $version;
        $amd64Sha = '71b0d58cc53f6bd72cf2f293e09e294b79c666d8';
        $arm64Sha = 'e0f0c06ebc5627e43b25475711e694450489ab00';

        // dpkg --print-architecture yields the Debian arch name (amd64/arm64).
        $select = 'ARCH="$(dpkg --print-architecture)"; '
            . 'case "$ARCH" in '
            . 'amd64) SC=supercronic-linux-amd64; SCSHA=' . $amd64Sha . ' ;; '
            . 'arm64) SC=supercronic-linux-arm64; SCSHA=' . $arm64Sha . ' ;; '
            . '*) echo "unsupported architecture: $ARCH" >&2; exit 1 ;; '
            . 'esac';

        $parts   = [];
        $parts[] = 'apt-get update -qq';
        $parts[] = 'apt-get install -y --no-install-recommends curl ca-certificates';
        $parts[] = 'rm -rf /var/lib/apt/lists/*';
        $parts[] = $select;
        $parts[] = 'curl -fsSLo /usr/local/bin/supercronic "' . $base . '/$SC"';
        $parts[] = 'echo "$SCSHA  /usr/local/bin/supercronic" | sha1sum -c -';
        $parts[] = 'chmod +x /usr/local/bin/supercronic';

        return 'RUN ' . implode(" \\\n    && ", $parts);
    }

    private function buildCrontab(ApplicationConfig $app): string
    {
        $lines = [];
        foreach ($app->crons as $cron) {
            // "# name" annotates each entry so supercronic's stdout logs are identifiable.
            $lines[] = '# ' . $cron->name;
            // supercronic execs the command directly (no shell), so a bare relative command like
            // "bin/cli ..." is not resolved against the workdir/PATH and fails with ENOENT. Wrap it
            // in "sh -c" so the command runs in a shell exactly like the worker services do.
            $command = trim($cron->command);
            $escaped = "'" . str_replace("'", "'\\''", $command) . "'";
            $lines[] = $cron->schedule . ' /bin/sh -c ' . $escaped;
        }

        return implode("\n", $lines) . "\n";
    }

    private function buildPhpIni(PhpConfig $php): string
    {
        $lines = [];
        foreach ($php->settings as $setting) {
            $lines[] = $setting->name . '=' . $setting->value;
        }

        return implode("\n", $lines) . "\n";
    }

    /** @param list<ExtensionConfig> $extensions */
    private function buildExtensionRun(array $extensions): string
    {
        // Dedupe so a config listing the same extension twice does not emit a
        // duplicate "pecl install <ext>", which fails on the second run
        // ("already installed ... install failed" → non-zero exit → build breaks).
        $extNames   = array_values(array_unique(
            array_map(static fn (ExtensionConfig $e) => $e->name, $extensions),
        ));
        $nativeExts = array_values(array_filter($extNames, fn (string $n) => ! $this->isPeclExtension($n)));
        $peclExts   = array_values(array_filter($extNames, fn (string $n) => $this->isPeclExtension($n)));

        $aptPackages = array_unique(array_merge(
            // unzip: so composer can extract packages. curl/wget/ca-certificates:
            // the FrankenPHP base image ships neither curl nor wget, yet build
            // hooks routinely fetch tools/binaries with them — guarantee both
            // (plus CA roots for TLS) so hooks don't fail with "not found" (exit 127).
            ['unzip', 'curl', 'wget', 'ca-certificates'],
            ...array_map(fn (string $name) => $this->aptDepsForExtension($name), $extNames),
        ));

        $parts   = [];
        $parts[] = 'apt-get update -qq';
        $parts[] = 'apt-get install -y --no-install-recommends ' . implode(' ', $aptPackages);
        $parts[] = 'rm -rf /var/lib/apt/lists/*';

        if ($nativeExts !== []) {
            $parts[] = 'docker-php-ext-install ' . implode(' ', $nativeExts);
        }

        foreach ($peclExts as $ext) {
            $parts[] = 'pecl install ' . $ext;
            $parts[] = 'docker-php-ext-enable ' . $ext;
        }

        return 'RUN ' . implode(" \\\n    && ", $parts);
    }

    private function isPeclExtension(string $extension): bool
    {
        return match ($extension) {
            'imagick', 'redis', 'xdebug', 'mongodb', 'igbinary', 'msgpack' => true,
            default => false,
        };
    }

    /** @return list<string> */
    private function aptDepsForExtension(string $extension): array
    {
        return match ($extension) {
            'intl'                         => ['libicu-dev'],
            'gd'                           => ['libpng-dev', 'libfreetype6-dev', 'libjpeg62-turbo-dev'],
            'zip'                          => ['libzip-dev'],
            'mbstring'                     => ['libonig-dev'],
            'pdo_pgsql', 'pgsql'           => ['libpq-dev'],
            'xsl'                          => ['libxslt-dev'],
            'imap'                         => ['libc-client-dev', 'libkrb5-dev'],
            'imagick'                      => ['libmagickwand-dev'],
            'curl'                         => ['libcurl4-openssl-dev'],
            'bz2'                          => ['libbz2-dev'],
            'enchant'                      => ['libenchant-2-dev'],
            'ffi'                          => ['libffi-dev'],
            'ftp'                          => ['libssl-dev'],
            'gmp'                          => ['libgmp-dev'],
            'ldap'                         => ['libldap2-dev', 'libsasl2-dev'],
            'pdo_dblib'                    => ['libfreetds-dev'],
            'pdo_odbc'                     => ['unixodbc-dev'],
            'pdo_sqlite', 'sqlite3'        => ['libsqlite3-dev'],
            'pspell'                       => ['libpspell-dev'],
            'readline'                     => ['libedit-dev'],
            'snmp'                         => ['libsnmp-dev', 'snmp'],
            'soap', 'xml', 'xmlreader',
            'xmlwriter', 'simplexml'       => ['libxml2-dev'],
            'sodium'                       => ['libsodium-dev'],
            'tidy'                         => ['libtidy-dev'],
            'mongodb'                      => ['libssl-dev'],
            default                        => [],
        };
    }

    /** @return list<string> */
    private function parseScriptLines(string $value): array
    {
        $result = [];
        foreach (explode("\n", $value) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $result[] = $trimmed;
        }

        return $result;
    }
}
