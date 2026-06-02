<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Metrics;

use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Model\WorkerMetrics;
use Tragwerk\Infrastructure\Ssh\RemoteShell;

use function preg_match_all;
use function preg_quote;
use function round;
use function sprintf;
use function str_contains;

/**
 * Scrapes FrankenPHP worker metrics for one environment over SSH.
 *
 * Each app container exposes Prometheus metrics on its internal Caddy admin endpoint
 * (localhost:2019/metrics, enabled via `servers { metrics }` in the generated Caddyfile). PHP is
 * present in the app image, so we fetch the endpoint from inside the container. Values are summed
 * across all of the environment's containers (and worker label series). The parse step
 * ({@see self::parse()}) is pure and unit-testable.
 */
final readonly class WorkerMetricsCollector
{
    public function __construct(private RemoteShell $shell)
    {
    }

    public function collect(
        Project $project,
        string $branch,
        Server $server,
        Credential $credential,
    ): WorkerMetrics|null {
        $dir = 'tragwerk/' . $project->id->toString() . '/' . $branch;

        $script = sprintf(
            <<<'SH'
            cd ~/%s 2>/dev/null || exit 0
            for c in $(docker compose ps -q 2>/dev/null); do
              docker exec "$c" php -r 'echo @file_get_contents("http://127.0.0.1:2019/metrics");' 2>/dev/null
            done
            SH,
            $dir,
        );

        return $this->parse($this->shell->run($server, $credential, $script));
    }

    public function parse(string $output): WorkerMetrics|null
    {
        // No FrankenPHP metrics means the environment is not running (or not in worker mode).
        if (! str_contains($output, 'frankenphp_')) {
            return null;
        }

        return new WorkerMetrics(
            totalWorkers: $this->sum($output, 'frankenphp_total_workers'),
            busyWorkers:  $this->sum($output, 'frankenphp_busy_workers'),
            readyWorkers: $this->sum($output, 'frankenphp_ready_workers'),
            queueDepth:   $this->sum($output, 'frankenphp_worker_queue_depth'),
            requestCount: $this->sum($output, 'frankenphp_worker_request_count'),
            crashes:      $this->sum($output, 'frankenphp_worker_crashes'),
            restarts:     $this->sum($output, 'frankenphp_worker_restarts'),
        );
    }

    /** Sums a Prometheus metric across all of its label series. */
    private function sum(string $output, string $metric): int
    {
        $pattern = '/^' . preg_quote($metric, '/') . '(?:\{[^}]*\})?\s+([0-9.eE+-]+)\s*$/m';

        if (preg_match_all($pattern, $output, $matches) === false) {
            return 0;
        }

        $sum = 0.0;
        foreach ($matches[1] as $value) {
            $sum += (float) $value;
        }

        return (int) round($sum);
    }
}
