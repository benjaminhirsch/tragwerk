<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Enum;

enum Locale: string
{
    public const Locale DEFAULT = self::EN_US;

    case DE_DE = 'de_DE';
    case EN_US = 'en_US';

    /** @psalm-pure */
    public function getNativeName(): string
    {
        return match ($this) {
            self::DE_DE => 'Deutsch',
            self::EN_US => 'English'
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
            self::EN_US => 'en'
        };
    }
}
