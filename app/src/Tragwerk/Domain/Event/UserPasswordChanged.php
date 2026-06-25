<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use SensitiveParameter;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class UserPasswordChanged
{
    public function __construct(
        public UserIdentifier $userId,
        #[SensitiveParameter]
        public string $passwordHash,
    ) {
    }
}
