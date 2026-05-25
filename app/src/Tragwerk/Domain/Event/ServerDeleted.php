<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\ValueObject\ServerIdentifier;

final readonly class ServerDeleted
{
    public function __construct(
        public ServerIdentifier $serverId,
    ) {
    }
}
