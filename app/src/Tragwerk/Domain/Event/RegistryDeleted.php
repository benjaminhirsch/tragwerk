<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\ValueObject\RegistryIdentifier;

final readonly class RegistryDeleted
{
    public function __construct(
        public RegistryIdentifier $registryId,
    ) {
    }
}
