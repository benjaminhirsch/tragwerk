<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Model\CronRun;

final readonly class CronRunsCollected
{
    /** @param list<CronRun> $runs */
    public function __construct(
        public array $runs,
    ) {
    }
}
