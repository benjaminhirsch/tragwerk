<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Entity\DeployJob;

final readonly class DeployJobCreated
{
    public function __construct(
        public DeployJob $job,
    ) {
    }
}
