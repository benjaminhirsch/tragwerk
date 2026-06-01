<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

final readonly class WorkerConfig
{
    public function __construct(
        public int|null $count = null,
        public int $maxRequests = 0,
    ) {
    }
}
