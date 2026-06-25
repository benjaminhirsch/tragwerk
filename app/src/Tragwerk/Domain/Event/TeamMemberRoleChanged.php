<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Enum\TeamRole;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class TeamMemberRoleChanged
{
    public function __construct(
        public TeamIdentifier $teamId,
        public UserIdentifier $userId,
        public TeamRole $role,
        public UserIdentifier $changedBy,
    ) {
    }
}
