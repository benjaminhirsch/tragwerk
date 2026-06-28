<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;

interface RegistryPrefixRepository
{
    public function upsert(
        ProjectIdentifier $projectId,
        RegistryIdentifier $registryId,
        string $appSlug,
        string $branchSlug,
    ): void;

    /** @return list<array{registry_id: string, app_slug: string, branch_slug: string}> */
    public function findByProject(ProjectIdentifier $projectId): array;

    /** @return list<array{app_slug: string, branch_slug: string}> */
    public function findByRegistry(RegistryIdentifier $registryId): array;

    /** Removes all registry prefix rows of the given project for the given branch slug. */
    public function deleteByProjectAndBranchSlug(ProjectIdentifier $projectId, string $branchSlug): void;
}
