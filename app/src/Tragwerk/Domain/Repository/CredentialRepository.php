<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Generator;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Entity;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityDeletionFailed;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Exception\Repository\EntityUpdateFailed;
use Tragwerk\Domain\ValueObject\CredentialIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;

interface CredentialRepository
{
    /**
     * @return Credential
     *
     * @throws EntityCreationFailed
     * @throws EntityHydrationFailed
     * @throws EntityNotFound
     */
    public function getById(CredentialIdentifier $id): Entity;

    /** @throws EntityCreationFailed */
    public function create(Credential $entity): void;

    /** @throws EntityUpdateFailed */
    public function update(Credential $entity): void;

    /** @throws EntityDeletionFailed */
    public function delete(CredentialIdentifier $id): void;

    /** @param CredentialIdentifier[]|null $ids */
    public function getAll(
        array|null $ids = null,
        TeamIdentifier|null $teamId = null,
    ): Generator;
}
