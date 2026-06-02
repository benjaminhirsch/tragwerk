<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Registry;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\RegistryRepository;

final readonly class DeleteRegistry
{
    public function __construct(private RegistryRepository $registryRepository)
    {
    }

    public function __invoke(Event\RegistryDeleted $event): void
    {
        $this->registryRepository->delete($event->registryId);
    }
}
