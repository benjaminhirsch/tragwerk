<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Application\Dto\Team\TeamUpdate;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class TeamUpdated
{
    public function __construct(
        public TeamIdentifier $teamId,
        public TeamUpdate $teamUpdate,
        public UserIdentifier $updatedBy,
    ) {
    }
}
