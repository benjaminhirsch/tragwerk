<?php

declare(strict_types=1);

namespace Tragwerk\Application\Translator;

use Tragwerk\Domain\Enum\Translatable;

abstract class Translator
{
    public const string DEFAULT_TEXT_DOMAIN = 'default';

    abstract public function getDefaultLocale(): string|null;

    abstract public function setDefaultLocale(string|null $locale): void;

    /** @param string[]|int[]|float[] $parameters */
    abstract public function translate(
        string|Translatable $message,
        array $parameters = [],
        string|null $domain = null,
        string|null $locale = null,
    ): string;

    /** @param string[]|int[]|float[] $parameters */
    public function translatePlural(
        string|Translatable $singularMessage,
        string|Translatable $pluralMessage,
        int $count,
        array $parameters = [],
        string|null $domain = null,
        string|null $locale = null,
    ): string {
        if ($count === 1) {
            return $this->translate(
                $singularMessage,
                $parameters,
                $domain,
                $locale,
            );
        }

        return $this->translate(
            $pluralMessage,
            $parameters,
            $domain,
            $locale,
        );
    }
}
