<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Generator;
use Tragwerk\Domain\Entity\Entity;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityDeletionFailed;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Exception\Repository\EntityUpdateFailed;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;

interface RegistryRepository
{
    /**
     * @return Registry
     *
     * @throws EntityCreationFailed
     * @throws EntityHydrationFailed
     * @throws EntityNotFound
     */
    public function getById(RegistryIdentifier $id): Entity;

    /** @throws EntityCreationFailed */
    public function create(Registry $entity): void;

    /** @throws EntityUpdateFailed */
    public function update(Registry $entity): void;

    /** @throws EntityDeletionFailed */
    public function delete(RegistryIdentifier $id): void;

    /** @return Generator<Registry> */
    public function getAll(TeamIdentifier|null $teamId = null): Generator;

    public function isAssignedToProject(RegistryIdentifier $id): bool;
}
