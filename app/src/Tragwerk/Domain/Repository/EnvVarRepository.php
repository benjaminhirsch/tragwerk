<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Generator;
use Tragwerk\Domain\Entity\EnvVar;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityDeletionFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Exception\Repository\EntityUpdateFailed;
use Tragwerk\Domain\ValueObject\EnvVarIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

interface EnvVarRepository
{
    /** @throws EntityNotFound */
    public function getById(EnvVarIdentifier $id): EnvVar;

    /** @throws EntityCreationFailed */
    public function create(EnvVar $var): void;

    /** @throws EntityUpdateFailed */
    public function update(EnvVar $var): void;

    /** @throws EntityDeletionFailed */
    public function delete(EnvVarIdentifier $id): void;

    /** @return Generator<EnvVar> */
    public function findByBranch(ProjectIdentifier $projectId, string $branch): Generator;

    /**
     * Returns inherited vars from the given ancestor branches, ordered by branch (for caller to apply precedence).
     *
     * @param list<string> $ancestorBranches
     *
     * @return Generator<EnvVar>
     */
    public function findInheritedFromAncestors(ProjectIdentifier $projectId, array $ancestorBranches): Generator;
}
