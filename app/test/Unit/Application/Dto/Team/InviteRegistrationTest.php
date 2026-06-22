<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Dto\Team;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Dto\Team\InviteRegistration;
use Tragwerk\Application\Exception\ValidationCollection;

final class InviteRegistrationTest extends TestCase
{
    #[Test]
    public function validInputConstructsAndCreatesUser(): void
    {
        $dto  = new InviteRegistration('Ada', 'Lovelace', 'supersecret', 'supersecret');
        $user = $dto->createUser('ada@example.com');

        self::assertSame('ada@example.com', $user->email);
        self::assertSame('Ada Lovelace', $user->fullName());
    }

    #[Test]
    public function mismatchedPasswordsAreRejected(): void
    {
        $this->expectException(ValidationCollection::class);

        new InviteRegistration('Ada', 'Lovelace', 'supersecret', 'different1');
    }

    #[Test]
    public function tooShortPasswordIsRejected(): void
    {
        $this->expectException(ValidationCollection::class);

        new InviteRegistration('Ada', 'Lovelace', 'short', 'short');
    }
}
