<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Dto\Variable;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Dto\Variable\VariableCreation;
use Tragwerk\Application\Exception\ValidationCollection;

final class VariableCreationTest extends TestCase
{
    #[Test]
    public function validInputConstructsAndDefaultsFlagsToFalse(): void
    {
        $dto = new VariableCreation('DATABASE_URL', 'postgres://x');

        self::assertSame('DATABASE_URL', $dto->key);
        self::assertSame('postgres://x', $dto->value);
        self::assertFalse($dto->isSecret);
        self::assertFalse($dto->isInherited);
    }

    #[Test]
    public function presentFlagsBecomeTrue(): void
    {
        $dto = new VariableCreation('KEY', 'v', '1', '1');

        self::assertTrue($dto->isSecret);
        self::assertTrue($dto->isInherited);
    }

    #[Test]
    public function emptyKeyIsRejected(): void
    {
        self::assertSame('key', $this->errorFieldFor('', 'value'));
    }

    #[Test]
    public function lowercaseKeyIsRejected(): void
    {
        self::assertSame('key', $this->errorFieldFor('database_url', 'value'));
    }

    #[Test]
    public function keyStartingWithDigitIsRejected(): void
    {
        self::assertSame('key', $this->errorFieldFor('1KEY', 'value'));
    }

    #[Test]
    public function emptyValueIsRejected(): void
    {
        self::assertSame('value', $this->errorFieldFor('KEY', ''));
    }

    private function errorFieldFor(string $key, string $value): string
    {
        try {
            new VariableCreation($key, $value);
        } catch (ValidationCollection $e) {
            return $e->validations[0]->name;
        }

        self::fail('Expected ValidationCollection');
    }
}
