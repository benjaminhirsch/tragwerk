<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Domain;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\DomainRepository;

final readonly class AddDomain
{
    public function __construct(private DomainRepository $domainRepository)
    {
    }

    public function __invoke(Event\DomainAdded $event): void
    {
        $this->domainRepository->create($event->domain);
    }
}
