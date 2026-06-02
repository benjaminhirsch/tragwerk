<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Application\Dto\Registry\Registry as RegistryDto;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class RegistryUpdated
{
    public function __construct(
        public Registry $registry,
        public RegistryDto $dto,
        public UserIdentifier $updatedBy,
    ) {
    }
}
