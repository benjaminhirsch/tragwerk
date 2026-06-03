<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Domain\Enum\SwarmNodeRole;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;

final class SwarmNode
{
    public function __construct(
        public ProjectIdentifier $projectId,
        public ServerIdentifier $serverId,
        public SwarmNodeRole $role,
        public bool $isStorage,
    ) {
    }
}
