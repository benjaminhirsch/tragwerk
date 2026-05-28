<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Entity\BuildLog;

final readonly class BuildLogCreated
{
    public function __construct(
        public BuildLog $log,
    ) {
    }
}
