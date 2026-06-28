<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use DateTimeImmutable;
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

    /** Latest deploy job for the project across all branches, or null. */
    public function getLatestByProject(ProjectIdentifier $projectId): DeployJob|null;

    /**
     * Counts deploy jobs for the given projects created since the given moment.
     *
     * @param list<string> $projectIds
     *
     * @return array{total: int, completed: int, failed: int}
     */
    public function countByProjectsSince(array $projectIds, DateTimeImmutable $since): array;

    /**
     * Most recent deploy jobs across the given projects, newest first.
     *
     * @param list<string> $projectIds
     *
     * @return list<DeployJob>
     */
    public function getRecentByProjects(array $projectIds, int $limit): array;

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

    public function hasAnyCompletedDeploy(ProjectIdentifier $projectId): bool;

    /** @return list<string> */
    public function getDeployedBranches(ProjectIdentifier $projectId): array;

    /** @return list<DeployJob> */
    public function getPagedByProjectAndBranch(
        ProjectIdentifier $projectId,
        string $branch,
        int $limit,
        int $offset,
    ): array;

    /** @throws EntityCreationFailed */
    public function create(DeployJob $entity): void;

    /** @throws EntityUpdateFailed */
    public function updateStatus(DeployJobIdentifier $id, DeployJobStatus $status): void;

    /** @throws EntityUpdateFailed */
    public function appendOutput(DeployJobIdentifier $id, string $text): void;

    /** Removes all deploy jobs of the given project on the given branch. */
    public function deleteByProjectAndBranch(ProjectIdentifier $projectId, string $branch): void;
}
