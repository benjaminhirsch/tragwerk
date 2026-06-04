<?php

declare(strict_types=1);

namespace Tragwerk\Domain\ValueObject;

use Override;
use Tragwerk\Domain\Enum\EntityType;

final readonly class EmailConfirmationIdentifier extends Uuid implements EntityIdentifier
{
    #[Override]
    public static function getEntityType(): EntityType
    {
        return EntityType::EMAIL_CONFIRMATION;
    }
}
