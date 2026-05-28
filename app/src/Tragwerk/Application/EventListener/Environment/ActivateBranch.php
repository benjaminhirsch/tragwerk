<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Environment;

use Tragwerk\Domain\Event\BranchActivated;
use Tragwerk\Domain\Repository\EnvironmentRepository;

final readonly class ActivateBranch
{
    public function __construct(
        private EnvironmentRepository $environmentRepository,
    ) {
    }

    public function __invoke(BranchActivated $event): void
    {
        $this->environmentRepository->activate($event->projectId, $event->branch);
    }
}
