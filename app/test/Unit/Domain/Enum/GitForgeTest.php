<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\Enum;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Enum\GitForge;

final class GitForgeTest extends TestCase
{
    #[Test]
    public function labelIsNonEmptyForEveryCase(): void
    {
        foreach (GitForge::cases() as $forge) {
            self::assertNotSame('', $forge->label(), $forge->name . ' has empty label');
        }
    }

    #[Test]
    public function tryFromRouteSlugResolvesKnownSlug(): void
    {
        self::assertSame(GitForge::GITHUB, GitForge::tryFromRouteSlug('github'));
    }

    #[Test]
    public function tryFromRouteSlugReturnsNullForUnknownSlug(): void
    {
        self::assertNull(GitForge::tryFromRouteSlug('does-not-exist'));
    }
}
