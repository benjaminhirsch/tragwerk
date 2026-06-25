<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\ValueObject\RecoveryCodeIdentifier;

final readonly class RecoveryCodeConsumed
{
    public function __construct(
        public RecoveryCodeIdentifier $id,
    ) {
    }
}
