<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Git;

use DateTimeImmutable;
use RuntimeException;

use function array_fill_keys;
use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function chmod;
use function count;
use function explode;
use function fclose;
use function file_put_contents;
use function implode;
use function in_array;
use function intval;
use function is_dir;
use function mkdir;
use function proc_close;
use function proc_open;
use function rtrim;
use function stream_get_contents;
use function trim;

use const PHP_INT_MAX;

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

    /**
     * Deletes a branch ref from the bare repository. Idempotent: a missing ref
     * is a no-op (e.g. when the branch was already removed on the remote).
     */
    public function deleteBranch(string $projectId, string $branch): void
    {
        $path = $this->getPath($projectId);
        if (! is_dir($path)) {
            return;
        }

        try {
            $this->run(['git', '-C', $path, 'update-ref', '-d', 'refs/heads/' . $branch]);
        } catch (RuntimeException) {
            // ref already gone — nothing to do
        }
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

    /**
     * Returns a map of branch → parent branch (null = root).
     * The parent is the direct ancestor branch with the shortest distance,
     * determined via merge-base: candidate is a parent only if its HEAD
     * equals the merge-base with the given branch (i.e. no divergence on
     * the candidate side).
     *
     * @return array<string, string|null>
     */
    public function getBranchParents(string $projectId): array
    {
        $branches = $this->getBranches($projectId);

        if (count($branches) <= 1) {
            return array_fill_keys($branches, null);
        }

        $path    = $this->getPath($projectId);
        $tips    = [];
        $parents = [];

        foreach ($branches as $branch) {
            try {
                $tips[$branch] = trim($this->run(['git', '-C', $path, 'rev-parse', $branch]));
            } catch (RuntimeException) {
                $tips[$branch] = '';
            }
        }

        $rootBranches = ['main', 'master'];

        foreach ($branches as $branch) {
            if (in_array($branch, $rootBranches, true)) {
                $parents[$branch] = null;
                continue;
            }

            $bestParent  = null;
            $minDistance = PHP_INT_MAX;

            foreach ($branches as $candidate) {
                if ($candidate === $branch || ($tips[$candidate] ?? '') === '') {
                    continue;
                }

                try {
                    $mergeBase = trim($this->run(['git', '-C', $path, 'merge-base', $branch, $candidate]));
                } catch (RuntimeException) {
                    continue;
                }

                try {
                    // Distance = commits in branch since divergence from candidate.
                    // mergeBase..branch stays correct even when candidate moved forward
                    // after this branch was created.
                    $range    = $mergeBase . '..' . $branch;
                    $distance = intval(trim($this->run(['git', '-C', $path, 'rev-list', '--count', $range])));
                } catch (RuntimeException) {
                    continue;
                }

                if ($distance < 0 || $distance > $minDistance) {
                    continue;
                }

                // On equal distance prefer root branches as parent
                if ($distance === $minDistance && ! in_array($candidate, $rootBranches, true)) {
                    continue;
                }

                $minDistance = $distance;
                $bestParent  = $candidate;
            }

            $parents[$branch] = $bestParent;
        }

        return $this->breakParentCycles($parents);
    }

    /**
     * Removes cycles that can occur when branches have been merged back into
     * each other (both would appear as mutual ancestors).
     *
     * @param  array<string, string|null> $parents
     *
     * @return array<string, string|null>
     */
    private function breakParentCycles(array $parents): array
    {
        foreach (array_keys($parents) as $branch) {
            $visited = [$branch => true];
            $current = $parents[$branch];

            while ($current !== null) {
                if (isset($visited[$current])) {
                    $parents[$branch] = null;
                    break;
                }

                $visited[$current] = true;
                $current           = $parents[$current] ?? null;
            }
        }

        return $parents;
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
