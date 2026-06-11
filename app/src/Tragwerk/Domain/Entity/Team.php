<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function count;
use function mb_strtoupper;
use function mb_substr;
use function str_word_count;
use function trim;

final class Team implements Entity
{
    public function __construct(
        public TeamIdentifier $id,
        public string $name,
        public UserIdentifier $ownerId,
        public TimestampImmutable $createdAt,
        public UserIdentifier $createdBy,
        public TimestampImmutable $updatedAt,
        public UserIdentifier $updatedBy,
    ) {
    }

    public function abbreviation(): string
    {
        return $this->name
            |> trim(...)
            |> (static fn ($s) => str_word_count($s, 1))
            |> (static fn ($words) => count($words) === 1
                ? mb_strtoupper(mb_substr($words[0], 0, 1))
                : mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1)));
    }
}
