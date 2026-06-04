<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Project;

use Throwable;
use Tragwerk\Application\Queue\Message\CleanupProjectDocker;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ServerRepository;

use function assert;
use function preg_replace;
use function strtolower;

final readonly class QueueDockerCleanup
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private ServerRepository $serverRepository,
        private Producer $producer,
    ) {
    }

    public function __invoke(Event\ProjectDeleted $event): void
    {
        try {
            $project = $this->projectRepository->getById($event->projectId);
            assert($project instanceof Project);

            $server = $this->serverRepository->getById($project->serverId);
            assert($server instanceof Server);

            if ($server->credentialId === null) {
                return;
            }

            $this->producer->sendMessage(new CleanupProjectDocker(
                projectId:    $event->projectId->toString(),
                projectSlug:  $this->slugify($project->name),
                host:         $server->host,
                port:         $server->port,
                credentialId: $server->credentialId->toString(),
            ));
        } catch (Throwable) {
            // do not block project deletion if cleanup enqueueing fails
        }
    }

    private function slugify(string $name): string
    {
        $slug = preg_replace('/[\s_]+/', '-', strtolower($name)) ?? '';

        return preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';
    }
}
