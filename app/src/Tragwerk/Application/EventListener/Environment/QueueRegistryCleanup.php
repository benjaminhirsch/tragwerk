<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Environment;

use Throwable;
use Tragwerk\Application\Queue\Message\PruneAllRegistryImages;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\RegistryPrefixRepository;

use function basename;
use function preg_replace;
use function strtolower;

final readonly class QueueRegistryCleanup
{
    public function __construct(
        private RegistryPrefixRepository $registryPrefixRepository,
        private Producer $producer,
    ) {
    }

    public function __invoke(Event\EnvironmentDeleted $event): void
    {
        try {
            $branchSlug = $this->slugify(basename($event->branch));
            $rows       = $this->registryPrefixRepository->findByProject($event->projectId);

            $byRegistry = [];
            foreach ($rows as $row) {
                if ($row['branch_slug'] !== $branchSlug) {
                    continue;
                }

                $byRegistry[$row['registry_id']][] = $row['app_slug'] . '-' . $row['branch_slug'] . '-';
            }

            foreach ($byRegistry as $registryId => $prefixes) {
                $this->producer->sendMessage(new PruneAllRegistryImages($registryId, $prefixes));
            }

            $this->registryPrefixRepository->deleteByProjectAndBranchSlug($event->projectId, $branchSlug);
        } catch (Throwable) {
            // do not block environment deletion if registry cleanup enqueueing fails
        }
    }

    private function slugify(string $name): string
    {
        $slug = preg_replace('/[\s_]+/', '-', strtolower($name)) ?? '';

        return preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';
    }
}
