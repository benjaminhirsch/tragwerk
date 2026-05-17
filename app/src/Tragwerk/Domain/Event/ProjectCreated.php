<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Application\Dto\Project\ProjectCreation;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class ProjectCreated
{
    public function __construct(
        public ProjectCreation $projectCreation,
        public UserIdentifier $createdBy,
    ) {
    }
}
