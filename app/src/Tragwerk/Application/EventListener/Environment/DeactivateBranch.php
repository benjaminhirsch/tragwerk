<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Environment;

use Tragwerk\Domain\Event\BranchDeactivated;
use Tragwerk\Domain\Repository\EnvironmentRepository;

final readonly class DeactivateBranch
{
    public function __construct(
        private EnvironmentRepository $environmentRepository,
    ) {
    }

    public function __invoke(BranchDeactivated $event): void
    {
        $this->environmentRepository->deactivate($event->projectId, $event->branch);
    }
}
