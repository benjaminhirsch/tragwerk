<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Project;

use Throwable;
use Tragwerk\Application\Queue\Message\PruneAllRegistryImages;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\RegistryPrefixRepository;

final readonly class QueueRegistryProjectCleanup
{
    public function __construct(
        private RegistryPrefixRepository $registryPrefixRepository,
        private Producer $producer,
    ) {
    }

    public function __invoke(Event\ProjectDeleted $event): void
    {
        try {
            $rows = $this->registryPrefixRepository->findByProject($event->projectId);

            $byRegistry = [];
            foreach ($rows as $row) {
                $byRegistry[$row['registry_id']][] = $row['app_slug'] . '-' . $row['branch_slug'] . '-';
            }

            foreach ($byRegistry as $registryId => $prefixes) {
                $this->producer->sendMessage(new PruneAllRegistryImages($registryId, $prefixes));
            }
        } catch (Throwable) {
            // do not block project deletion if cleanup enqueueing fails
        }
    }
}
