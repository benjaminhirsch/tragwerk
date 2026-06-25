<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Dto\Team;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Dto\Team\TeamRoleSelection;
use Tragwerk\Domain\Enum\TeamRole;

final class TeamRoleSelectionTest extends TestCase
{
    #[Test]
    public function resolvesAdmin(): void
    {
        self::assertSame(TeamRole::Admin, TeamRoleSelection::fromArray(['admin'], 0));
    }

    #[Test]
    public function resolvesMember(): void
    {
        self::assertSame(TeamRole::Member, TeamRoleSelection::fromArray(['member'], 0));
    }

    #[Test]
    public function ownerIsNotAssignableAndFallsBackToMember(): void
    {
        self::assertSame(TeamRole::Member, TeamRoleSelection::fromArray(['owner'], 0));
    }

    #[Test]
    public function unknownValueFallsBackToMember(): void
    {
        self::assertSame(TeamRole::Member, TeamRoleSelection::fromArray(['superuser'], 0));
    }

    #[Test]
    public function missingIndexFallsBackToMember(): void
    {
        self::assertSame(TeamRole::Member, TeamRoleSelection::fromArray([], 3));
    }
}
