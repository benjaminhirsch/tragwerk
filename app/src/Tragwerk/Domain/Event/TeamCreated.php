<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Application\Dto\Team\TeamCreation;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class TeamCreated
{
    public function __construct(
        public TeamCreation $teamCreation,
        public UserIdentifier $createdBy,
    ) {
    }
}
