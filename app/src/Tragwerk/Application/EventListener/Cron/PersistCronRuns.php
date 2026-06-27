<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Cron;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\CronRunRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

final readonly class PersistCronRuns
{
    public function __construct(private CronRunRepository $repository)
    {
    }

    public function __invoke(Event\CronRunsCollected $event): void
    {
        foreach ($event->runs as $run) {
            // Project id is parsed from a container's working-dir label; ignore anything malformed.
            if (! ProjectIdentifier::isValid($run->projectId)) {
                continue;
            }

            $this->repository->store($run);
        }
    }
}
