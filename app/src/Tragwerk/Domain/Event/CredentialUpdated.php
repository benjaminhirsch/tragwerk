<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Application\Dto\Credential\EditCredential;
use Tragwerk\Domain\ValueObject\CredentialIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class CredentialUpdated
{
    public function __construct(
        public CredentialIdentifier $credentialId,
        public EditCredential $credential,
        public UserIdentifier $updatedBy,
    ) {
    }
}
