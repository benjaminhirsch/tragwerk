<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class EmailChanged
{
    public function __construct(
        public UserIdentifier $userId,
        public string $newEmail,
    ) {
    }
}
