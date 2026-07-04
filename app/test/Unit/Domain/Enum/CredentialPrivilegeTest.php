<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\Enum;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Enum\CredentialPrivilege;

final class CredentialPrivilegeTest extends TestCase
{
    #[Test]
    public function rootHasNoSudoPrefix(): void
    {
        self::assertSame('', CredentialPrivilege::Root->sudoPrefix());
    }

    #[Test]
    public function sudoPrefixesWithNonInteractiveSudo(): void
    {
        self::assertSame('sudo -n ', CredentialPrivilege::Sudo->sudoPrefix());
    }

    #[Test]
    public function bothCasesHaveTranslatableNames(): void
    {
        foreach (CredentialPrivilege::cases() as $case) {
            self::assertNotSame('', $case->translatableName());
        }
    }

    #[Test]
    public function tryFromMapsKnownValues(): void
    {
        self::assertSame(CredentialPrivilege::Root, CredentialPrivilege::tryFrom('root'));
        self::assertSame(CredentialPrivilege::Sudo, CredentialPrivilege::tryFrom('sudo'));
    }

    #[Test]
    public function tryFromRejectsUnknownValue(): void
    {
        self::assertNull(CredentialPrivilege::tryFrom('superuser'));
    }
}
