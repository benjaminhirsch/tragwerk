<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Entity\SetupJob;

final readonly class SetupJobScheduled
{
    public function __construct(
        public SetupJob $job,
    ) {
    }
}
