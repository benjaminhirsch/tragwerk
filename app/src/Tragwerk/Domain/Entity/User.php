<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use SensitiveParameter;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function count;
use function mb_strtoupper;
use function mb_substr;
use function str_word_count;

final class User implements Entity
{
    public function __construct(
        public UserIdentifier $id,
        public string $email,
        public string $firstname,
        public string $lastname,
        #[SensitiveParameter]
        public PasswordHash $password,
        public TimestampImmutable $createdAt,
        public TimestampImmutable $updatedAt,
        public TeamIdentifier|null $lastActiveTeamId = null,
        public TimestampImmutable|null $confirmedAt = null,
    ) {
    }

    public function fullName(): string
    {
        return $this->firstname . ' ' . $this->lastname;
    }

    public function abbreviation(): string
    {
        return $this->fullName()
                |> (static fn ($s) => str_word_count($s, 1))
                |> (static fn ($words) => count($words) === 1
                    ? mb_strtoupper(mb_substr($words[0], 0, 1))
                    : mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1)));
    }
}
