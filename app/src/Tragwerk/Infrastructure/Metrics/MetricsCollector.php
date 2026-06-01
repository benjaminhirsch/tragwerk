<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Metrics;

use RuntimeException;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Model\ServerMetricSample;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Infrastructure\Ssh\RemoteShell;

use function array_map;
use function array_shift;
use function array_sum;
use function count;
use function explode;
use function max;
use function min;
use function preg_split;
use function str_starts_with;
use function trim;

/**
 * Samples host-level metrics from a server over SSH.
 *
 * Runs one dependency-free shell snippet (reads /proc + free + df, sampling /proc/stat twice for a
 * CPU delta) and parses the prefixed output lines into a {@see ServerMetricSample}. The CPU-delta
 * math lives in PHP ({@see self::parse()}) so it is unit-testable without SSH.
 */
final readonly class MetricsCollector
{
    private const string SNIPPET = <<<'SH'
        grep '^cpu ' /proc/stat
        sleep 1
        grep '^cpu ' /proc/stat
        echo "LOAD $(cut -d' ' -f1 /proc/loadavg)"
        awk '/^MemTotal:/{t=$2} /^MemAvailable:/{a=$2} END{print "MEM", t*1024, (t-a)*1024}' /proc/meminfo
        echo "DISK $(df -PB1 / | awk 'NR==2{print $2, $3}')"
        SH;

    public function __construct(private RemoteShell $shell)
    {
    }

    public function collect(Server $server, Credential $credential): ServerMetricSample
    {
        $output = $this->shell->run($server, $credential, self::SNIPPET);

        return $this->parse($server->id, $output);
    }

    public function parse(ServerIdentifier $serverId, string $output): ServerMetricSample
    {
        $cpuLines = [];
        $load1    = null;
        $memTotal = null;
        $memUsed  = null;
        $diskTot  = null;
        $diskUsed = null;

        foreach (explode("\n", trim($output)) as $line) {
            $line = trim($line);

            if (str_starts_with($line, 'cpu ')) {
                $cpuLines[] = $line;
            } elseif (str_starts_with($line, 'LOAD ')) {
                $load1 = (float) trim(explode(' ', $line, 2)[1]);
            } elseif (str_starts_with($line, 'MEM ')) {
                [$memTotal, $memUsed] = $this->twoInts($line);
            } elseif (str_starts_with($line, 'DISK ')) {
                [$diskTot, $diskUsed] = $this->twoInts($line);
            }
        }

        if (count($cpuLines) < 2 || $load1 === null || $memTotal === null || $diskTot === null) {
            throw new RuntimeException('Incomplete metrics output from server.');
        }

        return new ServerMetricSample(
            serverId:       $serverId,
            sampledAt:      TimestampImmutable::now(),
            cpuPercent:     $this->cpuPercent($cpuLines[0], $cpuLines[1]),
            memUsedBytes:   $memUsed ?? 0,
            memTotalBytes:  $memTotal,
            diskUsedBytes:  $diskUsed ?? 0,
            diskTotalBytes: $diskTot,
            load1:          $load1,
        );
    }

    /** @return array{int, int} */
    private function twoInts(string $line): array
    {
        $parts = $this->fields($line);
        array_shift($parts); // drop the prefix label

        return [(int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0)];
    }

    private function cpuPercent(string $first, string $second): float
    {
        [$total1, $idle1] = $this->cpuTotals($first);
        [$total2, $idle2] = $this->cpuTotals($second);

        $totalDelta = $total2 - $total1;
        if ($totalDelta <= 0) {
            return 0.0;
        }

        $busy = 1.0 - (($idle2 - $idle1) / $totalDelta);

        return max(0.0, min(100.0, $busy * 100.0));
    }

    /** @return array{int, int} [total, idle] — idle counts the idle + iowait jiffies. */
    private function cpuTotals(string $line): array
    {
        $fields = $this->fields($line);
        array_shift($fields); // drop the "cpu" label

        $values = array_map(static fn (string $v): int => (int) $v, $fields);
        $idle   = ($values[3] ?? 0) + ($values[4] ?? 0);

        return [(int) array_sum($values), $idle];
    }

    /** @return list<string> */
    private function fields(string $line): array
    {
        $parts = preg_split('/\s+/', trim($line));

        return $parts === false ? [] : $parts;
    }
}
