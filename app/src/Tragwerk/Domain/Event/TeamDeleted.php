<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\ValueObject\TeamIdentifier;

final readonly class TeamDeleted
{
    public function __construct(
        public TeamIdentifier $teamId,
    ) {
    }
}
