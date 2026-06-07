<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\EnvVar;

use Tragwerk\Domain\Event\EnvVarCreated;
use Tragwerk\Domain\Repository\EnvVarRepository;

final readonly class PersistEnvVar
{
    public function __construct(private EnvVarRepository $repository)
    {
    }

    public function __invoke(EnvVarCreated $event): void
    {
        $this->repository->create($event->var);
    }
}
