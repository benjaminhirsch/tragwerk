<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Dto\Variable;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Dto\Variable\VariableUpdate;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Domain\Entity\EnvVar;
use Tragwerk\Domain\ValueObject\EnvVarIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

final class VariableUpdateTest extends TestCase
{
    #[Test]
    public function valueIsOptionalOnUpdate(): void
    {
        $dto = new VariableUpdate('KEY');

        self::assertSame('', $dto->value);
    }

    #[Test]
    public function invalidKeyIsRejected(): void
    {
        $this->expectException(ValidationCollection::class);

        new VariableUpdate('bad key');
    }

    #[Test]
    public function applyToKeepsExistingSecretValueWhenBlank(): void
    {
        $existing = $this->envVar('OLD', 'secret-value', isSecret: true);
        $updated  = (new VariableUpdate('NEW', '', '1'))->applyTo($existing);

        self::assertSame('NEW', $updated->key);
        self::assertSame('secret-value', $updated->value);
        self::assertTrue($updated->isSecret);
    }

    #[Test]
    public function applyToReplacesValueWhenProvided(): void
    {
        $existing = $this->envVar('OLD', 'secret-value', isSecret: true);
        $updated  = (new VariableUpdate('NEW', 'fresh'))->applyTo($existing);

        self::assertSame('fresh', $updated->value);
    }

    private function envVar(string $key, string $value, bool $isSecret): EnvVar
    {
        $now = TimestampImmutable::now();

        return new EnvVar(
            EnvVarIdentifier::create(),
            ProjectIdentifier::create(),
            'main',
            $key,
            $value,
            $isSecret,
            false,
            $now,
            $now,
        );
    }
}
