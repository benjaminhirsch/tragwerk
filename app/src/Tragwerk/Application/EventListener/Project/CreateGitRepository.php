<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Project;

use Tragwerk\Domain\Event;
use Tragwerk\Infrastructure\Git\BareRepository;

final readonly class CreateGitRepository
{
    public function __construct(
        private BareRepository $bareRepository,
    ) {
    }

    public function __invoke(Event\ProjectCreated $event): void
    {
        $this->bareRepository->init($event->projectId->toString());
    }
}
