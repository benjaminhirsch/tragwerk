<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Dto;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Dto\UserRegistration;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;

use function array_map;

final class UserRegistrationTest extends TestCase
{
    #[Test]
    public function validInputConstructsAndCreatesUser(): void
    {
        $dto  = new UserRegistration('Ada', 'Lovelace', 'ada@example.com', 'supersecret', 'supersecret');
        $user = $dto->createUser();

        self::assertSame('ada@example.com', $user->email);
        self::assertSame('Ada Lovelace', $user->fullName());
    }

    #[Test]
    public function mismatchedPasswordsAreRejected(): void
    {
        self::assertContains('password2', $this->errorFields('a', 'b', 'e@x.de', 'supersecret', 'different1'));
    }

    #[Test]
    public function tooShortPasswordIsRejected(): void
    {
        self::assertContains('password1', $this->errorFields('a', 'b', 'e@x.de', 'short', 'short'));
    }

    /** @return array<string> */
    private function errorFields(string $f, string $l, string $email, string $p1, string $p2): array
    {
        try {
            new UserRegistration($f, $l, $email, $p1, $p2);
        } catch (ValidationCollection $e) {
            return array_map(static fn (ValidationError $v): string => $v->name, $e->validations);
        }

        self::fail('Expected ValidationCollection');
    }
}
