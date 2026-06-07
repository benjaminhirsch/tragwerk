<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\EnvVar;

use Tragwerk\Domain\Event\EnvVarDeleted;
use Tragwerk\Domain\Repository\EnvVarRepository;

final readonly class DeleteEnvVar
{
    public function __construct(private EnvVarRepository $repository)
    {
    }

    public function __invoke(EnvVarDeleted $event): void
    {
        $this->repository->delete($event->id);
    }
}
