<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Domain\ValueObject\CredentialIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final class Server implements Entity
{
    public function __construct(
        public ServerIdentifier $id,
        public string $name,
        public string $host,
        public CredentialIdentifier|null $credentialId,
        public ProjectIdentifier $projectId,
        public TimestampImmutable $createdAt,
        public UserIdentifier $createdBy,
        public TimestampImmutable $updatedAt,
        public UserIdentifier $updatedBy,
        public int $port = 22,
        public string|null $dockerVersion = null,
        public string|null $dockerComposeVersion = null,
    ) {
    }
}
