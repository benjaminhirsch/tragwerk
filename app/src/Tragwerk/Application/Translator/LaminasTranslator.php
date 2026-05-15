<?php

declare(strict_types=1);

namespace Tragwerk\Application\Translator;

use InvalidArgumentException;
use Laminas\Translator\TranslatorInterface;
use Stringable;
use Tragwerk\Domain\Enum\Translatable;

use function get_debug_type;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;
use function str_replace;

class LaminasTranslator extends Translator
{
    private string|null $defaultLocale = null;

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function getDefaultLocale(): string|null
    {
        return $this->defaultLocale;
    }

    public function setDefaultLocale(string|null $locale): void
    {
        $this->defaultLocale = $locale;
    }

    /** {@inheritDoc} */
    public function translate(
        string|Translatable $message,
        array $parameters = [],
        string|null $domain = null,
        string|null $locale = null,
    ): string {
        if ($message instanceof Translatable) {
            $message = $message->translatableName();
        }

        $translated = $this->translator->translate(
            $message,
            $domain ?? 'messages',
            $locale ?? $this->defaultLocale,
        );

        foreach ($parameters as $key => $value) {
            $key        = sprintf('{%s}', $key);
            $value      = self::convertToString($value);
            $translated = str_replace($key, $value, $translated);
        }

        return $translated;
    }

    private static function convertToString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if ($value === null) {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        throw new InvalidArgumentException(sprintf(
            'Unable to convert value of type %s to string',
            get_debug_type($value),
        ));
    }
}
