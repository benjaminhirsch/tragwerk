<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Dto;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Dto\PasswordResetApply;
use Tragwerk\Application\Exception\ValidationCollection;

final class PasswordResetApplyTest extends TestCase
{
    #[Test]
    public function matchingLongPasswordConstructs(): void
    {
        $dto = new PasswordResetApply('supersecret', 'supersecret');

        self::assertSame('supersecret', $dto->password1);
    }

    #[Test]
    public function mismatchedPasswordsAreRejected(): void
    {
        self::assertContains('password2', $this->errorFields('supersecret', 'different1'));
    }

    #[Test]
    public function tooShortPasswordIsRejected(): void
    {
        self::assertContains('password1', $this->errorFields('short', 'short'));
    }

    /** @return list<string> */
    private function errorFields(string $p1, string $p2): array
    {
        try {
            new PasswordResetApply($p1, $p2);
        } catch (ValidationCollection $e) {
            return array_map(static fn ($v) => $v->name, $e->validations);
        }

        self::fail('Expected ValidationCollection');
    }
}
