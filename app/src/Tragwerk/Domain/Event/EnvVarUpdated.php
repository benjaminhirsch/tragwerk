<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Entity\EnvVar;

final readonly class EnvVarUpdated
{
    public function __construct(
        public EnvVar $var,
    ) {
    }
}
