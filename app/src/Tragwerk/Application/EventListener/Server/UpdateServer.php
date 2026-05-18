<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Server;

use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function assert;

final readonly class UpdateServer
{
    public function __construct(private ServerRepository $serverRepository)
    {
    }

    public function __invoke(Event\ServerUpdated $event): void
    {
        $server = $this->serverRepository->getById($event->serverId);
        assert($server instanceof Server);

        $server->name      = $event->server->name;
        $server->host      = $event->server->host;
        $server->updatedAt = TimestampImmutable::now();
        $server->updatedBy = $event->updatedBy;

        $this->serverRepository->update($server);
    }
}
