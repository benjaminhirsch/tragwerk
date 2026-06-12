<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Application\Helper\AbbreviationHelper;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final class Team implements Entity, Abbreviation
{
    public function __construct(
        public TeamIdentifier $id,
        public string $name,
        public UserIdentifier $ownerId,
        public TimestampImmutable $createdAt,
        public UserIdentifier $createdBy,
        public TimestampImmutable $updatedAt,
        public UserIdentifier $updatedBy,
    ) {
    }

    public function abbreviation(): AbbreviationHelper
    {
        return new AbbreviationHelper($this->name);
    }
}
