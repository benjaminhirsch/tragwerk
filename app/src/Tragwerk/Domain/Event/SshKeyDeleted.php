<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\ValueObject\SshKeyIdentifier;

final readonly class SshKeyDeleted
{
    public function __construct(
        public SshKeyIdentifier $keyId,
    ) {
    }
}
