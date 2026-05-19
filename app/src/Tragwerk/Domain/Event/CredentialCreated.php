<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Application\Dto\Credential\Credential as CredentialDto;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class CredentialCreated
{
    public function __construct(
        public CredentialDto $credential,
        public UserIdentifier $createdBy,
        public ProjectIdentifier $projectId,
    ) {
    }
}
