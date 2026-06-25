<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Enum;

use Override;

use function _;
use function in_array;

enum TeamRole: string implements Translatable
{
    case Owner  = 'owner';
    case Admin  = 'admin';
    case Member = 'member';

    /** @phpstan-pure */
    #[Override]
    public function translatableName(): string
    {
        return match ($this) {
            self::Owner  => _('Owner'),
            self::Admin  => _('Admin'),
            self::Member => _('Member'),
        };
    }

    /**
     * @return TeamPermission[]
     *
     * @phpstan-pure
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => [
                TeamPermission::ViewTeam,
                TeamPermission::EditTeam,
                TeamPermission::ManageMembers,
                TeamPermission::DeleteTeam,
            ],
            self::Admin => [
                TeamPermission::ViewTeam,
                TeamPermission::EditTeam,
                TeamPermission::ManageMembers,
            ],
            self::Member => [
                TeamPermission::ViewTeam,
            ],
        };
    }

    /** @phpstan-pure */
    public function can(TeamPermission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /**
     * Roles that can be granted when inviting or managing members. Owner is excluded:
     * it is conferred only via explicit ownership transfer.
     *
     * @return list<self>
     *
     * @phpstan-pure
     */
    public static function assignable(): array
    {
        return [self::Admin, self::Member];
    }
}
