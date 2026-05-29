<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Entity\Domain;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

final readonly class DomainAdded
{
    public ProjectIdentifier $projectId;

    public function __construct(public Domain $domain)
    {
        $this->projectId = $domain->projectId;
    }
}
