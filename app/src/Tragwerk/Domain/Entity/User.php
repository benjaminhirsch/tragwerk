<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use SensitiveParameter;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final class User implements Entity
{
    public function __construct(
        public UserIdentifier $id,
        public string $email,
        public string $firstname,
        public string $lastname,
        public string|null $displayname,
        #[SensitiveParameter]
        public PasswordHash $password,
        public TimestampImmutable $createdAt,
        public TimestampImmutable $updatedAt,
    ) {
    }
}
