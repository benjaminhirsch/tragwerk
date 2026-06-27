<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Cron;

use Tragwerk\Domain\ValueObject\TimestampImmutable;

/**
 * A cron run reconstructed from supercronic JSON logs, before it is enriched with the owning
 * environment context (project/branch/app) and the human-readable job name from config.
 *
 * @see SupercronicLogParser
 */
final readonly class ParsedCronRun
{
    public function __construct(
        public string $command,
        public string|null $schedule,
        public TimestampImmutable $startedAt,
        public TimestampImmutable|null $finishedAt,
        public bool|null $succeeded,
        public string|null $output,
    ) {
    }
}
