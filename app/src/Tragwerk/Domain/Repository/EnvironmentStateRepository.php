<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Tragwerk\Domain\ValueObject\ProjectIdentifier;

interface EnvironmentStateRepository
{
    /** Marks the environment as disabled (paused). Idempotent. */
    public function disable(ProjectIdentifier $projectId, string $branch): void;

    /** Clears the disabled flag, e.g. on (re)deploy or deletion. Idempotent. */
    public function enable(ProjectIdentifier $projectId, string $branch): void;

    public function isDisabled(ProjectIdentifier $projectId, string $branch): bool;

    /**
     * @param string[] $branches
     *
     * @return array<string, bool> branch → disabled
     */
    public function disabledMap(ProjectIdentifier $projectId, array $branches): array;
}
