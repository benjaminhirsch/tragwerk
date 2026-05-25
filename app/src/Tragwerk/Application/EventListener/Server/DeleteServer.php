<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Server;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\ServerRepository;

final readonly class DeleteServer
{
    public function __construct(
        private ServerRepository $serverRepository,
    ) {
    }

    public function __invoke(Event\ServerDeleted $event): void
    {
        $this->serverRepository->delete($event->serverId);
    }
}
