<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Application\Dto\Server\ServerCreation;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class ServerCreated
{
    public function __construct(
        public ServerCreation $serverCreation,
        public UserIdentifier $createdBy,
        public ProjectIdentifier $projectId,
    ) {
    }
}
