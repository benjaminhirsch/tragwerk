<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Registry;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\RegistryRepository;

final readonly class CreateRegistry
{
    public function __construct(private RegistryRepository $registryRepository)
    {
    }

    public function __invoke(Event\RegistryCreated $event): void
    {
        $this->registryRepository->create($event->registry->createRegistry(
            $event->createdBy,
            $event->teamId,
            $event->registryId,
        ));
    }
}
