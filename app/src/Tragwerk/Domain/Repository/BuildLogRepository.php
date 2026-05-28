<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Generator;
use Tragwerk\Domain\Entity\BuildLog;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

interface BuildLogRepository
{
    public function create(BuildLog $log): void;

    /** @return Generator<BuildLog> */
    public function getByProjectAndBranch(ProjectIdentifier $projectId, string $branch): Generator;

    /** @return Generator<BuildLog> */
    public function getLatestByProjectAndBranch(
        ProjectIdentifier $projectId,
        string $branch,
        int $limit = 10,
    ): Generator;
}
