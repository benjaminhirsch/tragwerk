<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Model\EnvironmentMetrics;

final readonly class AppMetricsSampled
{
    public function __construct(
        public string $projectId,
        public string $branch,
        public EnvironmentMetrics $metrics,
    ) {
    }
}
