<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Entity\ProjectInvitation;

final readonly class ProjectInvitationCreated
{
    public function __construct(
        public ProjectInvitation $invitation,
    ) {
    }
}
