<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Entity\TeamInvitation;

final readonly class TeamInvitationCreated
{
    public function __construct(
        public TeamInvitation $invitation,
    ) {
    }
}
