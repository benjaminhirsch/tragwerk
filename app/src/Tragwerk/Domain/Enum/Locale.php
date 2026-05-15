<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Enum;

enum Locale: string
{
    public const Locale DEFAULT = self::DE_DE;

    case DE_DE = 'de_DE';
    case EN_EN = 'en_EN';

    /** @psalm-pure */
    public function getNativeName(): string
    {
        return match ($this) {
            self::DE_DE => 'Deutsch',
            self::EN_EN => 'English'
        };
    }

    /** @psalm-pure */
    public function getLocaleCode(): string
    {
        return $this->value;
    }

    /** @psalm-pure */
    public function getLanguageCode(): string
    {
        return match ($this) {
            self::DE_DE => 'de',
            self::EN_EN => 'en'
        };
    }
}
