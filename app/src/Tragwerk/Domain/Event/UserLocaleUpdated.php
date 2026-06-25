<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Event;

use Tragwerk\Domain\Enum\Locale;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class UserLocaleUpdated
{
    public function __construct(
        public UserIdentifier $userId,
        public Locale|null $locale,
    ) {
    }
}
