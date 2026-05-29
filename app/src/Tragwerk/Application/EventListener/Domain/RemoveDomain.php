<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Domain;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\DomainRepository;

final readonly class RemoveDomain
{
    public function __construct(private DomainRepository $domainRepository)
    {
    }

    public function __invoke(Event\DomainDeleted $event): void
    {
        $this->domainRepository->delete($event->domainId);
    }
}
