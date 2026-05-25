<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

final readonly class QueueMessageDeleted
{
    public function __construct(
        public string $id,
    ) {
    }
}
