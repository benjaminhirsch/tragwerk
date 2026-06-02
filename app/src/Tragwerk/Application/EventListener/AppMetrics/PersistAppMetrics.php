<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\AppMetrics;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\AppMetricRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

final readonly class PersistAppMetrics
{
    public function __construct(private AppMetricRepository $repository)
    {
    }

    public function __invoke(Event\AppMetricsSampled $event): void
    {
        // The project id is parsed from a container's working-dir label; ignore anything malformed.
        if (! ProjectIdentifier::isValid($event->projectId)) {
            return;
        }

        $this->repository->store(
            ProjectIdentifier::fromString($event->projectId),
            $event->branch,
            $event->metrics,
        );
    }
}
