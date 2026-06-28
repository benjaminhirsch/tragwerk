<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Environment;

use Throwable;
use Tragwerk\Application\Queue\Message\CleanupEnvironmentDocker;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ServerRepository;

use function assert;

final readonly class QueueDockerCleanup
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private ServerRepository $serverRepository,
        private Producer $producer,
    ) {
    }

    public function __invoke(Event\EnvironmentDeleted $event): void
    {
        try {
            $project = $this->projectRepository->getById($event->projectId);
            assert($project instanceof Project);

            $server = $this->serverRepository->getById($project->serverId);
            assert($server instanceof Server);

            if ($server->credentialId === null) {
                return;
            }

            $this->producer->sendMessage(new CleanupEnvironmentDocker(
                projectId:    $event->projectId->toString(),
                branch:       $event->branch,
                host:         $server->host,
                port:         $server->port,
                credentialId: $server->credentialId->toString(),
            ));
        } catch (Throwable) {
            // do not block environment deletion if cleanup enqueueing fails
        }
    }
}
