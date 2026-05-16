<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Docker;

use Tragwerk\Domain\Enum\HookType;
use Tragwerk\Domain\Model\ApplicationConfig;
use Tragwerk\Domain\Model\HookConfig;

use function array_filter;
use function array_values;
use function explode;
use function implode;
use function ltrim;
use function preg_replace;
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
        $lines[] = '';
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
