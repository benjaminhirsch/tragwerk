<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\Enum;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Enum\EntityType;

use function class_exists;

final class EntityTypeTest extends TestCase
{
    #[Test]
    public function getEntityClassNameMapsEveryCaseToAnExistingClass(): void
    {
        foreach (EntityType::cases() as $type) {
            self::assertTrue(
                class_exists($type->getEntityClassName()),
                $type->name . ' maps to a non-existent class',
            );
        }
    }

    #[Test]
    public function translatableNameIsNonEmptyForEveryCase(): void
    {
        foreach (EntityType::cases() as $type) {
            self::assertNotSame('', $type->translatableName(), $type->name . ' has empty name');
        }
    }
}
