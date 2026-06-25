<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\Enum;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Enum\TeamPermission;
use Tragwerk\Domain\Enum\TeamRole;

final class TeamRoleTest extends TestCase
{
    #[Test]
    public function ownerHasEveryPermission(): void
    {
        foreach (TeamPermission::cases() as $permission) {
            self::assertTrue(TeamRole::Owner->can($permission), $permission->value);
        }
    }

    #[Test]
    public function adminCanManageButNotDelete(): void
    {
        self::assertTrue(TeamRole::Admin->can(TeamPermission::ViewTeam));
        self::assertTrue(TeamRole::Admin->can(TeamPermission::EditTeam));
        self::assertTrue(TeamRole::Admin->can(TeamPermission::ManageMembers));
        self::assertFalse(TeamRole::Admin->can(TeamPermission::DeleteTeam));
    }

    #[Test]
    public function memberCanOnlyView(): void
    {
        self::assertTrue(TeamRole::Member->can(TeamPermission::ViewTeam));
        self::assertFalse(TeamRole::Member->can(TeamPermission::EditTeam));
        self::assertFalse(TeamRole::Member->can(TeamPermission::ManageMembers));
        self::assertFalse(TeamRole::Member->can(TeamPermission::DeleteTeam));
    }

    /** @return iterable<string, array{TeamRole, int}> */
    public static function permissionCountProvider(): iterable
    {
        yield 'owner'  => [TeamRole::Owner, 4];
        yield 'admin'  => [TeamRole::Admin, 3];
        yield 'member' => [TeamRole::Member, 1];
    }

    #[Test]
    #[DataProvider('permissionCountProvider')]
    public function permissionCountMatchesExpectation(TeamRole $role, int $expected): void
    {
        self::assertCount($expected, $role->permissions());
    }
}
