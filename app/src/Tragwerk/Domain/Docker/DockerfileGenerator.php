<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Docker;

use Tragwerk\Domain\Enum\HookType;
use Tragwerk\Domain\Model\ApplicationConfig;
use Tragwerk\Domain\Model\ExtensionConfig;
use Tragwerk\Domain\Model\HookConfig;

use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function explode;
use function implode;
use function ltrim;
use function preg_replace;
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
        $slug            = $this->slugify($app->name);
        $buildHooks      = $this->filterHooks($app->hooks, HookType::BUILD);
        $deployHooks     = $this->filterHooks($app->hooks, HookType::DEPLOY);
        $postDeployHooks = $this->filterHooks($app->hooks, HookType::POST_DEPLOY);
        $hasEntrypoint   = $deployHooks !== [] || $postDeployHooks !== [];

        return new DockerfileOutput(
            dockerfileName:    'Dockerfile.' . $slug,
            dockerfileContent: $this->buildDockerfile($app, $slug, $buildHooks, $hasEntrypoint),
            entrypointName:    $hasEntrypoint ? 'docker-entrypoint.' . $slug . '.sh' : null,
            entrypointContent: $hasEntrypoint ? $this->buildEntrypoint($deployHooks, $postDeployHooks) : null,
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
    ): string {
        $image      = $this->imageResolver->forApplication($app->type);
        $copySource = $app->root === '/' ? '.' : ltrim($app->root, '/');

        $lines   = [];
        $lines[] = 'FROM ' . $image;
        $lines[] = '';
        $lines[] = 'WORKDIR /app';

        if (str_starts_with($app->type->value, 'php:') && $app->extensions !== []) {
            $lines[] = '';
            $lines[] = $this->buildExtensionRun($app->extensions);
        }

        $lines[] = '';

        if (str_starts_with($app->type->value, 'php:')) {
            $lines[] = 'COPY --from=composer:latest /usr/bin/composer /usr/bin/composer';
            $lines[] = '';
        }

        $lines[] = 'COPY ' . $copySource . ' .';

        foreach ($buildHooks as $hook) {
            $scriptLines = $this->parseScriptLines($hook->value);
            if ($scriptLines === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = 'RUN ' . implode(" \\\n    && ", $scriptLines);
        }

        if ($hasEntrypoint) {
            $lines[] = '';
            $lines[] = 'COPY docker-entrypoint.' . $slug . '.sh /usr/local/bin/docker-entrypoint.sh';
            $lines[] = 'RUN chmod +x /usr/local/bin/docker-entrypoint.sh';
            $lines[] = '';
            $lines[] = 'ENTRYPOINT ["docker-entrypoint.sh"]';
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

    /** @param list<ExtensionConfig> $extensions */
    private function buildExtensionRun(array $extensions): string
    {
        $extNames    = array_map(static fn (ExtensionConfig $e) => $e->name, $extensions);
        $aptPackages = array_unique(array_merge(
            ...array_map(fn (string $name) => $this->aptDepsForExtension($name), $extNames),
        ));

        $parts = [];

        if ($aptPackages !== []) {
            $parts[] = 'apt-get update -qq';
            $parts[] = 'apt-get install -y --no-install-recommends ' . implode(' ', $aptPackages);
            $parts[] = 'rm -rf /var/lib/apt/lists/*';
        }

        $parts[] = 'docker-php-ext-install ' . implode(' ', $extNames);

        return 'RUN ' . implode(" \\\n    && ", $parts);
    }

    /** @return list<string> */
    private function aptDepsForExtension(string $extension): array
    {
        return match ($extension) {
            'intl'                  => ['libicu-dev'],
            'gd'                    => ['libpng-dev', 'libfreetype6-dev', 'libjpeg62-turbo-dev'],
            'zip'                   => ['libzip-dev'],
            'pdo_pgsql', 'pgsql'   => ['libpq-dev'],
            'xsl'                   => ['libxslt-dev'],
            'imap'                  => ['libc-client-dev', 'libkrb5-dev'],
            default                 => [],
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
