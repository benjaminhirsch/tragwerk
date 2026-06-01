<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Model\ServerMetricSample;

final readonly class ServerMetricsSampled
{
    public function __construct(
        public ServerMetricSample $sample,
    ) {
    }
}
