<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto\Team;

use Tragwerk\Domain\Enum\TeamRole;

use function in_array;

/**
 * Resolves a user-supplied role value (from an invite/role form) into a safe TeamRole.
 * Only assignable roles are honoured; anything else (empty, unknown, or Owner) falls back
 * to Member so a crafted request can never grant ownership.
 */
final readonly class TeamRoleSelection
{
    /** @param string[] $roles */
    public static function fromArray(array $roles, int $index): TeamRole
    {
        $raw  = $roles[$index] ?? '';
        $role = TeamRole::tryFrom($raw);

        if ($role === null || ! in_array($role, TeamRole::assignable(), true)) {
            return TeamRole::Member;
        }

        return $role;
    }
}
