<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Application\Dto\Server\Server as ServerDto;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class ServerCreated
{
    public function __construct(
        public ServerDto $server,
        public UserIdentifier $createdBy,
        public ProjectIdentifier $projectId,
        public ServerIdentifier $serverId,
    ) {
    }
}
