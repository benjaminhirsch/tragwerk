<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\Enum;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Enum\Locale;

final class LocaleTest extends TestCase
{
    #[Test]
    public function getLocaleCodeReturnsBackingValue(): void
    {
        self::assertSame('de_DE', Locale::DE_DE->getLocaleCode());
        self::assertSame('en_US', Locale::EN_US->getLocaleCode());
    }

    #[Test]
    public function getLanguageCodeReturnsIsoLanguagePart(): void
    {
        self::assertSame('de', Locale::DE_DE->getLanguageCode());
        self::assertSame('en', Locale::EN_US->getLanguageCode());
    }

    #[Test]
    public function getNativeNameReturnsLanguageInItsOwnTongue(): void
    {
        self::assertSame('Deutsch', Locale::DE_DE->getNativeName());
        self::assertSame('English', Locale::EN_US->getNativeName());
    }

    #[Test]
    public function defaultIsEnglish(): void
    {
        self::assertSame('en_US', Locale::DEFAULT->getLocaleCode());
    }
}
