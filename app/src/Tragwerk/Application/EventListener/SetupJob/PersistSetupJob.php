<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\SetupJob;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\SetupJobRepository;

final readonly class PersistSetupJob
{
    public function __construct(private SetupJobRepository $setupJobRepository)
    {
    }

    public function __invoke(Event\SetupJobScheduled $event): void
    {
        $this->setupJobRepository->create($event->job);
    }
}
