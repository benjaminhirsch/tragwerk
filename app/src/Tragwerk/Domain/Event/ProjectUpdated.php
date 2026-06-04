<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Application\Dto\Project\ProjectUpdate;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class ProjectUpdated
{
    public function __construct(
        public ProjectIdentifier $projectId,
        public ProjectUpdate $dto,
        public UserIdentifier $updatedBy,
    ) {
    }
}
