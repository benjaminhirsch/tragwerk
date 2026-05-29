<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\ValueObject\DomainIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

final readonly class DomainSetPrimary
{
    public function __construct(
        public DomainIdentifier $domainId,
        public ProjectIdentifier $projectId,
    ) {
    }
}
