<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Application\Dto\Project\ProjectUpdate;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class ProjectUpdated
{
    /** @param list<array{serverId: string, role: string, isStorage: bool}> $swarmNodes */
    public function __construct(
        public ProjectIdentifier $projectId,
        public ProjectUpdate $dto,
        public UserIdentifier $updatedBy,
        public array $swarmNodes = [],
    ) {
    }
}
