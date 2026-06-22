<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Dto\Team;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Dto\Team\TeamCreation;
use Tragwerk\Application\Exception\ValidationCollection;

final class TeamCreationTest extends TestCase
{
    #[Test]
    public function validNameWithoutInvitesConstructs(): void
    {
        $dto = new TeamCreation('Acme');

        self::assertSame('Acme', $dto->name);
    }

    #[Test]
    public function blankInviteEmailsAreIgnored(): void
    {
        $dto = new TeamCreation('Acme', ['', '  ', 'dev@example.com']);

        self::assertSame('Acme', $dto->name);
    }

    #[Test]
    public function emptyNameIsRejected(): void
    {
        $this->expectException(ValidationCollection::class);

        new TeamCreation('');
    }

    #[Test]
    public function invalidInviteEmailIsRejected(): void
    {
        try {
            new TeamCreation('Acme', ['not-an-email']);
        } catch (ValidationCollection $e) {
            self::assertSame('emailsToInvite[0]', $e->validations[0]->name);

            return;
        }

        self::fail('Expected ValidationCollection');
    }
}
