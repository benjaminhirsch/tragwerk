<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\EnvVar;

use Tragwerk\Domain\Event\EnvVarUpdated;
use Tragwerk\Domain\Repository\EnvVarRepository;

final readonly class UpdateEnvVar
{
    public function __construct(private EnvVarRepository $repository)
    {
    }

    public function __invoke(EnvVarUpdated $event): void
    {
        $this->repository->update($event->var);
    }
}
