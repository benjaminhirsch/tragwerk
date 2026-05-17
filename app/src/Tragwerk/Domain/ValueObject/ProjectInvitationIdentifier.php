<?php

declare(strict_types=1);

namespace Tragwerk\Domain\ValueObject;

use Override;
use Tragwerk\Domain\Enum\EntityType;

final readonly class ProjectInvitationIdentifier extends Uuid implements EntityIdentifier
{
    #[Override]
    public static function getEntityType(): EntityType
    {
        return EntityType::PROJECT_INVITATION;
    }
}
