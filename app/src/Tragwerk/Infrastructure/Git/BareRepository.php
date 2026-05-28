<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Git;

use DateTimeImmutable;
use RuntimeException;

use function array_filter;
use function array_map;
use function array_values;
use function chmod;
use function explode;
use function fclose;
use function file_put_contents;
use function implode;
use function is_dir;
use function mkdir;
use function proc_close;
use function proc_open;
use function rtrim;
use function stream_get_contents;
use function trim;

final readonly class BareRepository
{
    private const string LOG_SEPARATOR   = '---COMMIT---';
    private const string FIELD_SEPARATOR = "\x1E";

    public function __construct(
        private string $repositoriesPath,
        private string $appInternalUrl,
    ) {
    }

    public function init(string $projectId): void
    {
        $path = $this->getPath($projectId);
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $this->run(['git', 'init', '--bare', $path]);
        $this->installPostReceiveHook($path);
    }

    private function installPostReceiveHook(string $repoPath): void
    {
        $url  = $this->appInternalUrl;
        $hook = <<<SHELL
            #!/bin/sh
            while IFS=' ' read -r old_sha new_sha refname; do
                branch="\${refname#refs/heads/}"
                project_id=\$(basename "\$PWD")
                body="{\\"projectId\\":\\"\${project_id}\\",\\"branch\\":\\"\${branch}\\""
                body="\${body},\\"oldSha\\":\\"\${old_sha}\\",\\"newSha\\":\\"\${new_sha}\\"}"
                curl -s -f -X POST "{$url}/webhooks/git-push" \\
                    -H 'Content-Type: application/json' \\
                    -d "\${body}" >/dev/null 2>&1 || true
            done
            SHELL;

        $hookPath = $repoPath . '/hooks/post-receive';
        file_put_contents($hookPath, $hook);
        chmod($hookPath, 0755);
    }

    public function remove(string $projectId): void
    {
        $path = $this->getPath($projectId);
        if (! is_dir($path)) {
            return;
        }

        $this->run(['rm', '-rf', $path]);
    }

    /** @return string[] */
    public function getBranches(string $projectId): array
    {
        $output = $this->run([
            'git',
            '-C',
            $this->getPath($projectId),
            'for-each-ref',
            '--format=%(refname:short)',
            'refs/heads/',
        ]);

        return array_values(array_filter(
            array_map(trim(...), explode("\n", $output)),
            static fn (string $b) => $b !== '',
        ));
    }

    /** @return Commit[] */
    public function getCommits(string $projectId, string $branch, int $limit = 20): array
    {
        $sep    = self::FIELD_SEPARATOR;
        $format = implode($sep, ['%H', '%h', '%an', '%ae', '%s', '%at']);

        $output = $this->run([
            'git',
            '-C',
            $this->getPath($projectId),
            'log',
            $branch,
            '--format=' . $format . "\n" . self::LOG_SEPARATOR,
            '--max-count=' . $limit,
        ]);

        $blocks = array_filter(
            array_map(trim(...), explode(self::LOG_SEPARATOR, $output)),
            static fn (string $b) => $b !== '',
        );

        $commits = [];
        foreach ($blocks as $block) {
            $fields = explode($sep, trim($block));
            if (! isset($fields[5])) {
                continue;
            }

            $commits[] = new Commit(
                hash:        $fields[0],
                shortHash:   $fields[1],
                authorName:  $fields[2],
                authorEmail: $fields[3],
                subject:     $fields[4],
                committedAt: new DateTimeImmutable('@' . $fields[5]),
            );
        }

        return $commits;
    }

    public function getFileContent(string $projectId, string $commitSha, string $path): string|null
    {
        try {
            return $this->run(['git', '-C', $this->getPath($projectId), 'show', $commitSha . ':' . $path]);
        } catch (RuntimeException) {
            return null;
        }
    }

    public function exists(string $projectId): bool
    {
        return is_dir($this->getPath($projectId));
    }

    public function getPath(string $projectId): string
    {
        return rtrim($this->repositoriesPath, '/') . '/' . $projectId;
    }

    /** @param list<string> $command */
    private function run(array $command): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if ($process === false) {
            throw new RuntimeException('Failed to start git process: ' . implode(' ', $command));
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException('Git command failed: ' . ($stderr ?: implode(' ', $command)));
        }

        return $stdout !== false ? $stdout : '';
    }
}
