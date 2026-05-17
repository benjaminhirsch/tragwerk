<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ProjectInvitationIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final class ProjectInvitation implements Entity
{
    public function __construct(
        public ProjectInvitationIdentifier $id,
        public ProjectIdentifier $projectId,
        public string $email,
        public string $token,
        public TimestampImmutable $invitedAt,
        public UserIdentifier $invitedBy,
    ) {
    }
}
