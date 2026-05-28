<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\ValueObject\ProjectIdentifier;

final readonly class BranchActivated
{
    public function __construct(
        public ProjectIdentifier $projectId,
        public string $branch,
    ) {
    }
}
