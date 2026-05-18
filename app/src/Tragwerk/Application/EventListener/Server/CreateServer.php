<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Server;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\ServerRepository;

final readonly class CreateServer
{
    public function __construct(private ServerRepository $serverRepository)
    {
    }

    public function __invoke(Event\ServerCreated $event): void
    {
        $this->serverRepository->create($event->server->createServer(
            $event->createdBy,
            $event->projectId,
        ));
    }
}
