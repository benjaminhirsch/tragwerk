<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Metrics;

use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Model\EnvironmentMetrics;
use Tragwerk\Infrastructure\Ssh\RemoteShell;

use function array_slice;
use function count;
use function explode;
use function implode;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function round;
use function str_contains;
use function str_starts_with;
use function strpos;
use function substr;
use function trim;

use const PREG_SET_ORDER;

/**
 * Scrapes FrankenPHP + Caddy HTTP metrics for environments over SSH.
 *
 * App containers expose Prometheus metrics on their internal Caddy admin endpoint
 * (localhost:2019/metrics, enabled via `servers { metrics }` in the generated Caddyfile). PHP is
 * present in the app image, so the endpoint is fetched from inside the container. Values are summed
 * across an environment's containers and label series. The parse step ({@see self::parse()}) is pure
 * and unit-testable.
 *
 * {@see self::collectServer()} discovers all running environments on a host in one SSH connection
 * (background ticker); the live KPI tiles read the persisted samples from the database.
 */
final readonly class EnvironmentMetricsCollector
{
    public function __construct(private RemoteShell $shell)
    {
    }

    /**
     * Discovers every running environment on the host (one SSH connection) and returns the metrics
     * grouped per environment. "Active" therefore means "actually running".
     *
     * @return list<array{projectId: string, branch: string, metrics: EnvironmentMetrics}>
     */
    public function collectServer(Server $server, Credential $credential): array
    {
        $script = <<<'SH'
            CL=com.docker.compose.project.working_dir
            TL=tragwerk.working_dir
            for c in $(docker ps -q 2>/dev/null); do
              wd=$(docker inspect -f "{{ index .Config.Labels \"$CL\" }}" "$c" 2>/dev/null)
              if [ -z "$wd" ]; then
                wd=$(docker inspect -f "{{ index .Config.Labels \"$TL\" }}" "$c" 2>/dev/null)
              fi
              case "$wd" in
                */tragwerk/*/*) ;;
                *) continue ;;
              esac
              echo "===ENV $wd"
              docker exec "$c" php -r 'echo @file_get_contents("http://127.0.0.1:2019/metrics");' 2>/dev/null
            done
            SH;

        return $this->parseServer($this->shell->run($server, $credential, $script));
    }

    /**
     * Splits the per-server output (blocks prefixed by "===ENV <working_dir>") into one metrics
     * sample per environment. Multiple containers of the same environment are concatenated and summed.
     *
     * @return list<array{projectId: string, branch: string, metrics: EnvironmentMetrics}>
     */
    public function parseServer(string $output): array
    {
        /** @var array<string, string> $blocks workingDir → concatenated metrics text */
        $blocks     = [];
        $currentDir = null;

        foreach (explode("\n", $output) as $line) {
            if (str_starts_with($line, '===ENV ')) {
                $currentDir            = trim(substr($line, 7));
                $blocks[$currentDir] ??= '';

                continue;
            }

            if ($currentDir === null) {
                continue;
            }

            $blocks[$currentDir] .= $line . "\n";
        }

        $result = [];
        foreach ($blocks as $dir => $text) {
            $env = $this->parseWorkingDir($dir);
            if ($env === null) {
                continue;
            }

            $metrics = $this->parse($text);
            if ($metrics === null) {
                continue;
            }

            $result[] = ['projectId' => $env[0], 'branch' => $env[1], 'metrics' => $metrics];
        }

        return $result;
    }

    public function parse(string $output): EnvironmentMetrics|null
    {
        // Require at least Caddy HTTP metrics (always present when servers{metrics} is enabled).
        // FrankenPHP worker metrics are additionally available in worker mode; they default to 0
        // in classic mode. A completely empty/unrecognised output means the env is not running.
        $hasFrankenphp = str_contains($output, 'frankenphp_');
        $hasCaddy      = str_contains($output, 'caddy_http_');

        if (! $hasFrankenphp && ! $hasCaddy) {
            return null;
        }

        return new EnvironmentMetrics(
            totalWorkers:  $this->sum($output, 'frankenphp_total_workers'),
            busyWorkers:   $this->sum($output, 'frankenphp_busy_workers'),
            readyWorkers:  $this->sum($output, 'frankenphp_ready_workers'),
            queueDepth:    $this->sum($output, 'frankenphp_worker_queue_depth'),
            totalThreads:  $this->sum($output, 'frankenphp_total_threads'),
            busyThreads:   $this->sum($output, 'frankenphp_busy_threads'),
            requestCount:  $this->sum($output, 'frankenphp_worker_request_count'),
            crashes:       $this->sum($output, 'frankenphp_worker_crashes'),
            restarts:      $this->sum($output, 'frankenphp_worker_restarts'),
            requestsTotal: $this->sum($output, 'caddy_http_requests_total'),
            requests5xx:   $this->sumWhere($output, 'caddy_http_requests_total', 'code', '5'),
            requests4xx:   $this->sumWhere($output, 'caddy_http_requests_total', 'code', '4'),
            durationSumMs: (int) round($this->sumFloat($output, 'caddy_http_request_duration_seconds_sum') * 1000),
            durationCount: $this->sum($output, 'caddy_http_request_duration_seconds_count'),
            inFlight:      $this->sum($output, 'caddy_http_requests_in_flight'),
        );
    }

    /** @return array{string, string}|null [projectId, branch] parsed from a .../tragwerk/{id}/{branch} path */
    private function parseWorkingDir(string $dir): array|null
    {
        $pos = strpos($dir, 'tragwerk/');
        if ($pos === false) {
            return null;
        }

        $segments = explode('/', trim(substr($dir, $pos + 9), '/'));
        if (count($segments) < 2 || $segments[0] === '' || $segments[1] === '') {
            return null;
        }

        // Branch may contain slashes (e.g. "feature/x"); keep everything after the project id.
        return [$segments[0], implode('/', array_slice($segments, 1))];
    }

    private function sum(string $output, string $metric): int
    {
        return (int) round($this->sumFloat($output, $metric));
    }

    /** Sums a Prometheus metric across all of its label series. */
    private function sumFloat(string $output, string $metric): float
    {
        $pattern = '/^' . preg_quote($metric, '/') . '(?:\{[^}]*\})?\s+([0-9.eE+-]+)\s*$/m';

        if (preg_match_all($pattern, $output, $matches) === false) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($matches[1] as $value) {
            $sum += (float) $value;
        }

        return $sum;
    }

    /**
     * Sums only the series whose given label's value starts with the prefix
     * (e.g. label "code" prefix "5" → all 5xx responses).
     */
    private function sumWhere(string $output, string $metric, string $label, string $valuePrefix): int
    {
        $pattern = '/^' . preg_quote($metric, '/') . '\{([^}]*)\}\s+([0-9.eE+-]+)\s*$/m';

        if (preg_match_all($pattern, $output, $matches, PREG_SET_ORDER) === false) {
            return 0;
        }

        $sum = 0.0;
        foreach ($matches as $match) {
            if (preg_match('/\b' . preg_quote($label, '/') . '="([^"]*)"/', $match[1], $lm) !== 1) {
                continue;
            }

            if (! str_starts_with($lm[1], $valuePrefix)) {
                continue;
            }

            $sum += (float) $match[2];
        }

        return (int) round($sum);
    }
}
