<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Domain\ValueObject\EntityIdentifier;

interface Entity
{
    public EntityIdentifier $id { get; }
}
