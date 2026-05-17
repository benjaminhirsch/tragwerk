<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Helper;

use Tragwerk\Domain\Enum\EntityType;

final readonly class EntityHelper
{
    public static function getDbTableName(EntityType $entityType): string
    {
        return match ($entityType) {
            EntityType::USER => 'users',
            EntityType::SERVER => 'servers',
        };
    }
}
