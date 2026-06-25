<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Domain\Enum\TeamRole;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TeamInvitationIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final class TeamInvitation implements Entity
{
    public function __construct(
        public TeamInvitationIdentifier $id,
        public TeamIdentifier $teamId,
        public string $email,
        public string $token,
        public TimestampImmutable $invitedAt,
        public UserIdentifier $invitedBy,
        public TeamRole $role = TeamRole::Member,
    ) {
    }
}
