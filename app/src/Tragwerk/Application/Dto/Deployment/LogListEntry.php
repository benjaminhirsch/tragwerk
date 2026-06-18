<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto\Deployment;

use Tragwerk\Domain\Entity\BuildLog;
use Tragwerk\Domain\Entity\DeployJob;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

/**
 * Unified view-model entry for the deployment logs master list, merging
 * deploy jobs (with live terminal output) and build logs (point-in-time
 * messages) into a single chronologically sortable list.
 */
final readonly class LogListEntry
{
    private function __construct(
        public string $kind,
        public string $id,
        public TimestampImmutable $sortAt,
        public DeployJob|null $deployJob = null,
        public BuildLog|null $buildLog = null,
    ) {
    }

    public static function fromDeployJob(DeployJob $job): self
    {
        return new self('deploy', $job->id->toString(), $job->createdAt, deployJob: $job);
    }

    public static function fromBuildLog(BuildLog $log): self
    {
        return new self('build', $log->id->toString(), $log->createdAt, buildLog: $log);
    }
}
