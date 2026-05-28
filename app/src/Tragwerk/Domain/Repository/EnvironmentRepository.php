<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Tragwerk\Domain\ValueObject\ProjectIdentifier;

interface EnvironmentRepository
{
    /** @return string[] */
    public function getActiveBranches(ProjectIdentifier $projectId): array;

    public function isActive(ProjectIdentifier $projectId, string $branch): bool;

    public function activate(ProjectIdentifier $projectId, string $branch): void;

    public function deactivate(ProjectIdentifier $projectId, string $branch): void;
}
