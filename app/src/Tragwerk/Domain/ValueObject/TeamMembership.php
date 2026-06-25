<?php

declare(strict_types=1);

namespace Tragwerk\Domain\ValueObject;

use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Enum\TeamRole;

final readonly class TeamMembership
{
    public function __construct(
        public User $user,
        public TeamRole $role,
    ) {
    }
}
