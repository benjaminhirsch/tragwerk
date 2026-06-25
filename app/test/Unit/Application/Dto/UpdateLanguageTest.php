<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Dto;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Dto\UpdateLanguage;
use Tragwerk\Application\Exception\ValidationError;
use Tragwerk\Domain\Enum\Locale;

final class UpdateLanguageTest extends TestCase
{
    #[Test]
    public function germanIsResolvedToEnum(): void
    {
        $dto = new UpdateLanguage('de_DE');

        self::assertSame(Locale::DE_DE, $dto->locale);
    }

    #[Test]
    public function englishIsResolvedToEnum(): void
    {
        $dto = new UpdateLanguage('en_US');

        self::assertSame(Locale::EN_US, $dto->locale);
    }

    #[Test]
    public function emptySelectionMeansAutomatic(): void
    {
        $dto = new UpdateLanguage('');

        self::assertNull($dto->locale);
    }

    #[Test]
    public function invalidLocaleIsRejected(): void
    {
        try {
            new UpdateLanguage('xx_XX');
        } catch (ValidationError $e) {
            self::assertSame('locale', $e->name);

            return;
        }

        self::fail('Expected ValidationError');
    }
}
