<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Project;

use Tragwerk\Domain\Event;
use Tragwerk\Infrastructure\Git\BareRepository;

final readonly class DeleteGitRepository
{
    public function __construct(
        private BareRepository $bareRepository,
    ) {
    }

    public function __invoke(Event\ProjectDeleted $event): void
    {
        $this->bareRepository->remove($event->projectId->toString());
    }
}
