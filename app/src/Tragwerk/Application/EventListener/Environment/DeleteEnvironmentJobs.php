<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Environment;

use Throwable;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\DeployJobRepository;

final readonly class DeleteEnvironmentJobs
{
    public function __construct(
        private DeployJobRepository $deployJobRepository,
    ) {
    }

    public function __invoke(Event\EnvironmentDeleted $event): void
    {
        try {
            $this->deployJobRepository->deleteByProjectAndBranch($event->projectId, $event->branch);
        } catch (Throwable) {
            // do not block environment deletion if tracking cleanup fails
        }
    }
}
