<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;

final readonly class TeamMemberAdded
{
    public function __construct(
        public Team $team,
        public User $user,
    ) {
    }
}
