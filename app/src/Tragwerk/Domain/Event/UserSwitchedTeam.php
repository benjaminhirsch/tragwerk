<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class UserSwitchedTeam
{
    public function __construct(
        public UserIdentifier $userId,
        public TeamIdentifier $teamId,
    ) {
    }
}
