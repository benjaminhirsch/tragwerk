<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

final readonly class CronDefinitionConfig
{
    public function __construct(
        public string $name,
        public string $command,
        public string $schedule,
    ) {
    }
}
