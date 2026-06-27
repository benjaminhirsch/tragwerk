<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use DateTimeImmutable;
use Tragwerk\Domain\Model\CronRun;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

interface CronRunRepository
{
    /**
     * Idempotent upsert keyed by (project_id, branch, command, started_at): re-ingesting the same
     * run (the ticker reads overlapping log windows) updates the finishing state rather than
     * inserting a duplicate.
     */
    public function store(CronRun $run): void;

    /** @return list<CronRun> Most recent runs for an environment, newest first. */
    public function recent(ProjectIdentifier $projectId, string $branch, int $limit = 50): array;

    /**
     * The latest run per job (by command) for an environment, keyed by command.
     *
     * @return array<string, CronRun>
     */
    public function latestPerJob(ProjectIdentifier $projectId, string $branch): array;

    public function pruneOlderThan(DateTimeImmutable $threshold): int;
}
