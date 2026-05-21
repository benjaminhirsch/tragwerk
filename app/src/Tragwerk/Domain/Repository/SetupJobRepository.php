<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Tragwerk\Domain\Entity\Entity;
use Tragwerk\Domain\Entity\SetupJob;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Exception\Repository\EntityUpdateFailed;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\SetupJobIdentifier;

interface SetupJobRepository
{
    /**
     * @throws EntityNotFound
     * @throws EntityHydrationFailed
     */
    public function getById(SetupJobIdentifier $id): Entity;

    public function getLatestForServer(ServerIdentifier $serverId): SetupJob|null;

    /**
     * @param ServerIdentifier[] $serverIds
     *
     * @return string[]          server ID strings that have a completed setup job
     */
    public function getCompletedServerIds(array $serverIds): array;

    /** @throws EntityCreationFailed */
    public function create(SetupJob $entity): void;

    /** @throws EntityUpdateFailed */
    public function update(SetupJob $entity): void;

    /** @throws EntityUpdateFailed */
    public function appendOutput(SetupJobIdentifier $id, string $text): void;
}
