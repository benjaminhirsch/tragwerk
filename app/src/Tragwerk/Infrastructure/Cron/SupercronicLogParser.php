<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Cron;

use DateTimeImmutable;
use Throwable;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function explode;
use function is_array;
use function is_scalar;
use function is_string;
use function json_decode;
use function mb_substr;
use function rtrim;
use function trim;
use function usort;

/**
 * Reconstructs cron runs from a supercronic container's JSON logs (`supercronic -json`).
 *
 * supercronic logs each run as logrus JSON lines sharing `job.command`/`job.position` and an
 * `iteration` counter: a `msg=starting` line, zero or more output lines (tagged with `channel`),
 * and a terminal `msg="job succeeded"` / `msg="job failed"` line. Lines are correlated by
 * (job.position, iteration) into one {@see ParsedCronRun}. Pure and unit-testable.
 */
final readonly class SupercronicLogParser
{
    private const int OUTPUT_LIMIT = 4000;

    /** @return list<ParsedCronRun> */
    public function parse(string $logs): array
    {
        // One accumulator slot per run, correlated by (command, position, iteration), kept in
        // parallel maps keyed by that composite key.
        /** @var array<string, string> $command */
        $command = [];
        /** @var array<string, string|null> $schedule */
        $schedule = [];
        /** @var array<string, TimestampImmutable|null> $started */
        $started = [];
        /** @var array<string, TimestampImmutable|null> $finished */
        $finished = [];
        /** @var array<string, bool|null> $succeeded */
        $succeeded = [];
        /** @var array<string, string> $output */
        $output = [];
        /** @var array<string, TimestampImmutable|null> $seen */
        $seen = [];

        foreach (explode("\n", $logs) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            /** @var array<string, mixed>|null $obj */
            $obj = json_decode($line, true);
            if (! is_array($obj)) {
                continue;
            }

            $cmd = is_string($obj['job.command'] ?? null) ? $obj['job.command'] : null;
            if ($cmd === null) {
                continue;
            }

            $position  = isset($obj['job.position']) && is_scalar($obj['job.position'])
                ? (string) $obj['job.position'] : '';
            $iteration = isset($obj['iteration']) && is_scalar($obj['iteration'])
                ? (string) $obj['iteration'] : '';
            $key       = $cmd . '|' . $position . '|' . $iteration;

            $time = $this->parseTime(is_string($obj['time'] ?? null) ? $obj['time'] : null);

            if (! isset($command[$key])) {
                $command[$key]   = $cmd;
                $schedule[$key]  = null;
                $started[$key]   = null;
                $finished[$key]  = null;
                $succeeded[$key] = null;
                $output[$key]    = '';
                $seen[$key]      = $time;
            }

            if (is_string($obj['job.schedule'] ?? null)) {
                $schedule[$key] = $obj['job.schedule'];
            }

            $seen[$key] ??= $time;

            $msg = is_string($obj['msg'] ?? null) ? $obj['msg'] : '';

            // Output lines carry a channel (stdout/stderr); lifecycle lines do not.
            if (isset($obj['channel']) && $msg !== '') {
                $output[$key] .= $msg . "\n";

                continue;
            }

            if ($msg === 'starting') {
                $started[$key] = $time ?? $started[$key];

                continue;
            }

            if ($msg === 'job succeeded') {
                $finished[$key]  = $time ?? $seen[$key];
                $succeeded[$key] = true;

                continue;
            }

            if ($msg !== 'job failed') {
                continue;
            }

            $finished[$key]  = $time ?? $seen[$key];
            $succeeded[$key] = false;

            if (! is_string($obj['error'] ?? null) || $obj['error'] === '') {
                continue;
            }

            $output[$key] .= $obj['error'] . "\n";
        }

        $result = [];
        foreach ($command as $key => $cmd) {
            $start = $started[$key] ?? $seen[$key];
            if ($start === null) {
                continue;
            }

            $text = rtrim($output[$key]);

            $result[] = new ParsedCronRun(
                command:    $cmd,
                schedule:   $schedule[$key],
                startedAt:  $start,
                finishedAt: $finished[$key],
                succeeded:  $succeeded[$key],
                output:     $text === '' ? null : mb_substr($text, 0, self::OUTPUT_LIMIT),
            );
        }

        usort($result, static function (ParsedCronRun $a, ParsedCronRun $b): int {
            return $a->startedAt->toString() <=> $b->startedAt->toString();
        });

        return $result;
    }

    private function parseTime(string|null $raw): TimestampImmutable|null
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            // supercronic emits RFC3339 (may end in "Z"); the DateTimeImmutable constructor parses
            // both that and numeric offsets, unlike createFromFormat with a fixed pattern.
            return TimestampImmutable::fromDateTime(new DateTimeImmutable($raw));
        } catch (Throwable) {
            return null;
        }
    }
}
