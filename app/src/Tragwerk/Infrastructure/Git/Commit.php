<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Git;

use DateTimeImmutable;

final readonly class Commit
{
    public function __construct(
        public string $hash,
        public string $shortHash,
        public string $authorName,
        public string $authorEmail,
        public string $subject,
        public DateTimeImmutable $committedAt,
    ) {
    }
}
