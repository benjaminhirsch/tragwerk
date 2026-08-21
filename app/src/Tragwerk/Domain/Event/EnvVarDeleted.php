<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\ValueObject\EnvVarIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

final readonly class EnvVarDeleted
{
    public function __construct(
        public EnvVarIdentifier $id,
        public ProjectIdentifier $projectId,
        /** NULL for a project-scoped variable — see Entity\EnvVar. */
        public string|null $branch,
        public bool $wasInherited,
    ) {
    }
}
