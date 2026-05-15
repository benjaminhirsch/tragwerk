<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\ValueObject;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final class UserIdentifierTest extends TestCase
{
    private const string VALID_UUID = '550e8400-e29b-41d4-a716-446655440000';

    #[Test]
    public function isEqualToSameIdentifierWithSameUuid(): void
    {
        $a = UserIdentifier::fromString(self::VALID_UUID);
        $b = UserIdentifier::fromString(self::VALID_UUID);

        self::assertTrue($a->isEqualTo($b));
    }

    #[Test]
    public function createReturnsUniqueIdentifiers(): void
    {
        $a = UserIdentifier::create();
        $b = UserIdentifier::create();

        self::assertFalse($a->isEqualTo($b));
    }
}
