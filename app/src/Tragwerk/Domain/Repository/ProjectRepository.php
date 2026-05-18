<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Generator;
use Tragwerk\Domain\Entity\Entity;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityDeletionFailed;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Exception\Repository\EntityUpdateFailed;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

interface ProjectRepository
{
    /**
     * @return Project
     *
     * @throws EntityCreationFailed
     * @throws EntityHydrationFailed
     * @throws EntityNotFound
     */
    public function getById(ProjectIdentifier $id): Entity;

    /** @throws EntityCreationFailed */
    public function create(Project $entity): void;

    /** @throws EntityUpdateFailed */
    public function update(Project $entity): void;

    /** @throws EntityDeletionFailed */
    public function delete(ProjectIdentifier $id): void;

    /**
     * @param ProjectIdentifier[]|null $ids
     * @param string[]|null            $names
     */
    public function getAll(
        array|null $ids = null,
        array|null $names = null,
    ): Generator;

    /**
     * @param UserIdentifier[] $userIds
     *
     * @throws EntityCreationFailed
     */
    public function assignUsers(ProjectIdentifier $projectId, array $userIds): void;

    /** @return Generator<Project> */
    public function getByUserId(UserIdentifier $userId): Generator;
}
