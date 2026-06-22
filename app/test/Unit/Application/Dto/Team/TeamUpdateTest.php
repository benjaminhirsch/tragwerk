<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Dto\Team;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Dto\Team\TeamUpdate;
use Tragwerk\Application\Exception\ValidationCollection;

final class TeamUpdateTest extends TestCase
{
    #[Test]
    public function validInputConstructs(): void
    {
        $dto = new TeamUpdate('Acme', ['dev@example.com'], ['user-id']);

        self::assertSame('Acme', $dto->name);
        self::assertSame(['user-id'], $dto->usersToRemove);
    }

    #[Test]
    public function emptyNameIsRejected(): void
    {
        $this->expectException(ValidationCollection::class);

        new TeamUpdate('');
    }

    #[Test]
    public function invalidInviteEmailIsRejected(): void
    {
        try {
            new TeamUpdate('Acme', ['bad']);
        } catch (ValidationCollection $e) {
            self::assertSame('emailsToInvite[0]', $e->validations[0]->name);

            return;
        }

        self::fail('Expected ValidationCollection');
    }
}
