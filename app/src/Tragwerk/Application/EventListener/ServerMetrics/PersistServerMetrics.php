<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\ServerMetrics;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\ServerMetricRepository;

final readonly class PersistServerMetrics
{
    public function __construct(private ServerMetricRepository $repository)
    {
    }

    public function __invoke(Event\ServerMetricsSampled $event): void
    {
        $this->repository->store($event->sample);
    }
}
