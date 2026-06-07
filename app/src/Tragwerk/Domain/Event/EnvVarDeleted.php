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
        public string $branch,
        public bool $wasInherited,
    ) {
    }
}
