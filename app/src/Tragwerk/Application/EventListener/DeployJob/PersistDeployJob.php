<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\DeployJob;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\DeployJobRepository;

final readonly class PersistDeployJob
{
    public function __construct(private DeployJobRepository $deployJobRepository)
    {
    }

    public function __invoke(Event\DeployJobCreated $event): void
    {
        $this->deployJobRepository->create($event->job);
    }
}
