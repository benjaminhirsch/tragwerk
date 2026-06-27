<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

use Tragwerk\Domain\ValueObject\TimestampImmutable;

/**
 * A single execution of a cron job inside an application's {@see /} cron sidecar, reconstructed
 * from the container's supercronic (`-json`) logs by the cron:sample ticker.
 *
 * `finishedAt`/`succeeded` are null while a run is still in progress (or its finish line has not
 * been ingested yet); `store()` upserts the finishing state once it arrives.
 */
final readonly class CronRun
{
    public function __construct(
        public string $projectId,
        public string $branch,
        public string $appSlug,
        public string $jobName,
        public string $command,
        public string|null $schedule,
        public TimestampImmutable $startedAt,
        public TimestampImmutable|null $finishedAt = null,
        public bool|null $succeeded = null,
        public string|null $output = null,
    ) {
    }
}
