<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Application\Dto\Registry\Registry as RegistryDto;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class RegistryCreated
{
    public function __construct(
        public RegistryDto $registry,
        public UserIdentifier $createdBy,
        public TeamIdentifier $teamId,
        public RegistryIdentifier $registryId,
    ) {
    }
}
