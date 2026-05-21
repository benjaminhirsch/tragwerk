<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\SetupJob;

use Tragwerk\Application\Queue\Message\RunSetupJob;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Domain\Event;

final readonly class ScheduleSetupJob
{
    public function __construct(private Producer $producer)
    {
    }

    public function __invoke(Event\SetupJobScheduled $event): void
    {
        $this->producer->sendMessage(new RunSetupJob($event->job->id));
    }
}
