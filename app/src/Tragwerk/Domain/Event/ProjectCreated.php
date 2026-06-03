<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Application\Dto\Project\ProjectCreation;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class ProjectCreated
{
    /** @param list<array{serverId: string, role: string, isStorage: bool}> $swarmNodes */
    public function __construct(
        public ProjectCreation $dto,
        public ProjectIdentifier $projectId,
        public TeamIdentifier $teamId,
        public UserIdentifier $createdBy,
        public array $swarmNodes = [],
    ) {
    }
}
