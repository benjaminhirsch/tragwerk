<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\BuildLog;

use Tragwerk\Domain\Event\BuildLogCreated;
use Tragwerk\Domain\Repository\BuildLogRepository;

final readonly class PersistBuildLog
{
    public function __construct(
        private BuildLogRepository $repository,
    ) {
    }

    public function __invoke(BuildLogCreated $event): void
    {
        $this->repository->create($event->log);
    }
}
