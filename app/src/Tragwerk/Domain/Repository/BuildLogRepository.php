<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Generator;
use Tragwerk\Domain\Entity\BuildLog;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\ValueObject\BuildLogIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

interface BuildLogRepository
{
    public function create(BuildLog $log): void;

    /**
     * @throws EntityNotFound
     * @throws EntityHydrationFailed
     */
    public function getById(BuildLogIdentifier $id): BuildLog;

    /** @return Generator<BuildLog> */
    public function getByProjectAndBranch(ProjectIdentifier $projectId, string $branch): Generator;

    /** @return Generator<BuildLog> */
    public function getLatestByProjectAndBranch(
        ProjectIdentifier $projectId,
        string $branch,
        int $limit = 10,
    ): Generator;
}
