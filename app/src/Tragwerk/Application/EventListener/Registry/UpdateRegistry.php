<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Registry;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

final readonly class UpdateRegistry
{
    public function __construct(private RegistryRepository $registryRepository)
    {
    }

    public function __invoke(Event\RegistryUpdated $event): void
    {
        $r             = $event->registry;
        $r->name       = $event->dto->name;
        $r->url        = $event->dto->url;
        $r->repository = $event->dto->repository;
        $r->username   = $event->dto->username;
        $r->password   = $event->dto->password;
        $r->updatedBy  = $event->updatedBy;
        $r->updatedAt  = TimestampImmutable::now();

        $this->registryRepository->update($r);
    }
}
