<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class TeamMembersInvited
{
    /**
     * @param string[] $emails Raw email inputs, index-aligned with $roles.
     * @param string[] $roles  Raw role values; resolved per index (Owner is never honoured).
     */
    public function __construct(
        public TeamIdentifier $teamId,
        public array $emails,
        public array $roles,
        public UserIdentifier $invitedBy,
    ) {
    }
}
