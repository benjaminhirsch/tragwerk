<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Dto\Variable;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Dto\Variable\VariableCreation;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Domain\ValueObject\EnvVarIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

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
    public function defaultScopeIsEnvironment(): void
    {
        $dto = new VariableCreation('KEY', 'v');

        self::assertFalse($dto->isProjectScoped);
    }

    #[Test]
    public function projectScopeIsRecognised(): void
    {
        $dto = new VariableCreation('KEY', 'v', null, null, VariableCreation::SCOPE_PROJECT);

        self::assertTrue($dto->isProjectScoped);
    }

    #[Test]
    public function projectScopeForcesInheritedOff(): void
    {
        // A project-wide var already applies everywhere, so "inherited" is meaningless — and the
        // database rejects the combination with a CHECK constraint.
        $dto = new VariableCreation('KEY', 'v', null, '1', VariableCreation::SCOPE_PROJECT);

        self::assertTrue($dto->isProjectScoped);
        self::assertFalse($dto->isInherited);
    }

    #[Test]
    public function projectScopedVarIsCreatedWithoutBranch(): void
    {
        $dto = new VariableCreation('KEY', 'v', null, null, VariableCreation::SCOPE_PROJECT);
        $var = $dto->createEnvVar(EnvVarIdentifier::create(), ProjectIdentifier::create(), null);

        self::assertNull($var->branch);
    }

    #[Test]
    public function emptyKeyIsRejected(): void
    {
        self::assertSame('key', $this->errorFieldFor('', 'value'));
    }

    #[Test]
    public function lowercaseKeyIsNormalisedToUppercase(): void
    {
        // The key input renders uppercase (.key-input), so rejecting what the user was shown as
        // valid would be a trap.
        $dto = new VariableCreation('database_url', 'postgres://x');

        self::assertSame('DATABASE_URL', $dto->key);
    }

    #[Test]
    public function surroundingWhitespaceIsTrimmed(): void
    {
        self::assertSame('DATABASE_URL', (new VariableCreation('  database_url  ', 'x'))->key);
    }

    #[Test]
    public function keyWithUnsupportedCharactersIsRejected(): void
    {
        self::assertSame('key', $this->errorFieldFor('DATABASE-URL', 'value'));
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
