<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Registry;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function max;
use function trim;

final readonly class UpdateRegistry
{
    public function __construct(private RegistryRepository $registryRepository)
    {
    }

    public function __invoke(Event\RegistryUpdated $event): void
    {
        $r                 = $event->registry;
        $r->name           = $event->dto->name;
        $r->url            = $event->dto->url;
        $r->repository     = $event->dto->repository;
        $r->username       = $event->dto->username;
        $r->pruningEnabled = $event->dto->pruningEnabled;
        $r->keepTags       = max(1, $event->dto->keepTags);
        $r->updatedBy      = $event->updatedBy;
        $r->updatedAt      = TimestampImmutable::now();

        if (trim($event->dto->password) !== '') {
            $r->password = $event->dto->password;
        }

        $this->registryRepository->update($r);
    }
}
