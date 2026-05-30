<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Tragwerk\Domain\Entity\Domain;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityDeletionFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Exception\Repository\EntityUpdateFailed;
use Tragwerk\Domain\ValueObject\DomainIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

interface DomainRepository
{
    /** @throws EntityNotFound */
    public function getById(DomainIdentifier $id): Domain;

    /** @throws EntityCreationFailed */
    public function create(Domain $domain): void;

    /** @throws EntityDeletionFailed */
    public function delete(DomainIdentifier $id): void;

    /** @return list<Domain> */
    public function findByProject(ProjectIdentifier $projectId): array;

    /** @return list<Domain> */
    public function findByEnvironment(ProjectIdentifier $projectId, string $branch): array;

    /** @throws EntityUpdateFailed */
    public function clearPrimary(ProjectIdentifier $projectId, string $branch): void;

    /** @throws EntityUpdateFailed */
    public function setPrimary(DomainIdentifier $id): void;
}
