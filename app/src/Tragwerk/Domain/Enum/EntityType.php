<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Enum;

use Override;
use Tragwerk\Domain\Entity;

use function _;

enum EntityType: string implements Translatable
{
    case USER = 'USER';

    /** @phpstan-pure  */
    #[Override]
    public function translatableName(): string
    {
        return match ($this) {
            self::USER => _('User'),
        };
    }

    /**
     * @return class-string
     *
     * @phpstan-pure
     */
    public function getEntityClassName(): string
    {
        return match ($this) {
            self::USER => Entity\User::class,
        };
    }
}
