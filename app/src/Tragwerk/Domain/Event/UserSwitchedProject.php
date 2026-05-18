<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class UserSwitchedProject
{
    public function __construct(
        public UserIdentifier $userId,
        public ProjectIdentifier $projectId,
    ) {
    }
}
