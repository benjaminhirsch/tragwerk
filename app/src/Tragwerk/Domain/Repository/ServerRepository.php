<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Generator;
use Tragwerk\Domain\Entity\Entity;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityDeletionFailed;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Exception\Repository\EntityUpdateFailed;
use Tragwerk\Domain\ValueObject\ServerIdentifier;

interface ServerRepository
{
    /**
     * @return Server
     *
     * @throws EntityCreationFailed
     * @throws EntityHydrationFailed
     * @throws EntityNotFound
     */
    public function getById(ServerIdentifier $id): Entity;

    /** @throws EntityCreationFailed */
    public function create(Server $entity): void;

    /** @throws EntityUpdateFailed */
    public function update(Server $entity): void;

    /** @throws EntityDeletionFailed */
    public function delete(ServerIdentifier $id): void;

    /**
     * @param ServerIdentifier[]|null $ids
     * @param string[]|null           $names
     */
    public function getAll(
        array|null $ids = null,
        array|null $names = null,
    ): Generator;
}
