<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Application\Helper\AbbreviationHelper;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final class Project implements Entity, Abbreviation
{
    public function __construct(
        public ProjectIdentifier $id,
        public string $name,
        public ServerIdentifier $serverId,
        public TeamIdentifier $teamId,
        public TimestampImmutable $createdAt,
        public UserIdentifier $createdBy,
        public TimestampImmutable $updatedAt,
        public UserIdentifier $updatedBy,
        public RegistryIdentifier $registryId,
    ) {
    }

    public function abbreviation(): AbbreviationHelper
    {
        return new AbbreviationHelper($this->name);
    }
}
