<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Environment;

use Throwable;
use Tragwerk\Domain\Event;
use Tragwerk\Infrastructure\Git\BareRepository;

final readonly class DeleteGitBranch
{
    public function __construct(
        private BareRepository $bareRepository,
    ) {
    }

    public function __invoke(Event\EnvironmentDeleted $event): void
    {
        try {
            $this->bareRepository->deleteBranch($event->projectId->toString(), $event->branch);
        } catch (Throwable) {
            // do not block environment deletion if branch removal fails
        }
    }
}
