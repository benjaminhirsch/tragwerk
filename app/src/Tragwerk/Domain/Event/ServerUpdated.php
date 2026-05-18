<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Application\Dto\Server\Server as ServerDto;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class ServerUpdated
{
    public function __construct(
        public ServerIdentifier $serverId,
        public ServerDto $server,
        public UserIdentifier $updatedBy,
    ) {
    }
}
