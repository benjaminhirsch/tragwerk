<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Entity\RecoveryCode;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class RecoveryCodesGenerated
{
    /** @param list<RecoveryCode> $codes */
    public function __construct(
        public UserIdentifier $userId,
        public array $codes,
    ) {
    }
}
