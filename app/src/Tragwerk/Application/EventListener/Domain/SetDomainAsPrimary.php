<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Domain;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\DomainRepository;

final readonly class SetDomainAsPrimary
{
    public function __construct(private DomainRepository $domainRepository)
    {
    }

    public function __invoke(Event\DomainSetPrimary $event): void
    {
        $this->domainRepository->clearPrimary($event->projectId);
        $this->domainRepository->setPrimary($event->domainId);
    }
}
