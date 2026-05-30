<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Tragwerk\Domain\Entity\DeployJob;
use Tragwerk\Domain\Entity\Entity;
use Tragwerk\Domain\Enum\DeployJobStatus;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Exception\Repository\EntityUpdateFailed;
use Tragwerk\Domain\ValueObject\DeployJobIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

interface DeployJobRepository
{
    /**
     * @throws EntityNotFound
     * @throws EntityHydrationFailed
     */
    public function getById(DeployJobIdentifier $id): Entity;

    public function getLatestByProjectAndBranch(ProjectIdentifier $projectId, string $branch): DeployJob|null;

    /**
     * Returns all pending and running jobs, ordered oldest-first (i.e. deploy order).
     *
     * @return list<DeployJob>
     */
    public function getActiveByProjectAndBranch(ProjectIdentifier $projectId, string $branch): array;

    /**
     * @param string[] $branches
     *
     * @return array<string, DeployJobStatus> branch → latest status
     */
    public function getLatestStatusByProjectAndBranches(ProjectIdentifier $projectId, array $branches): array;

    public function hasCompletedDeploy(ProjectIdentifier $projectId, string $branch): bool;

    /** @throws EntityCreationFailed */
    public function create(DeployJob $entity): void;

    /** @throws EntityUpdateFailed */
    public function updateStatus(DeployJobIdentifier $id, DeployJobStatus $status): void;

    /** @throws EntityUpdateFailed */
    public function appendOutput(DeployJobIdentifier $id, string $text): void;
}
