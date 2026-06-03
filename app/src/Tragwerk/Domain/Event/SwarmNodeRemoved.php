<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;

final readonly class SwarmNodeRemoved
{
    public function __construct(
        public ProjectIdentifier $projectId,
        public ServerIdentifier $serverId,
    ) {
    }
}
