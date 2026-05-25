<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\ValueObject\CredentialIdentifier;

final readonly class CredentialDeleted
{
    public function __construct(
        public CredentialIdentifier $credentialId,
    ) {
    }
}
