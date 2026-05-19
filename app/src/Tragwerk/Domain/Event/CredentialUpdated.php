<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Application\Dto\Credential\Credential as CredentialDto;
use Tragwerk\Domain\ValueObject\CredentialIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class CredentialUpdated
{
    public function __construct(
        public CredentialIdentifier $credentialId,
        public CredentialDto $credential,
        public UserIdentifier $updatedBy,
    ) {
    }
}
