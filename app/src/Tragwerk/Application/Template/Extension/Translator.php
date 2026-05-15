<?php

declare(strict_types=1);

namespace Tragwerk\Application\Template\Extension;

use League\Plates\Engine;
use League\Plates\Extension\ExtensionInterface;
use Tragwerk\Application\Translator\Translator as BaseTranslator;
use Tragwerk\Domain\Enum\Translatable;
use Override;

final class Translator implements ExtensionInterface
{
    public function __construct(
        private readonly BaseTranslator $translator,
    ) {
    }

    #[Override]
    public function register(Engine $engine): void
    {
        $engine->registerFunction('t', [$this, 'translateSingular']);
        $engine->registerFunction('tp', [$this, 'translatePlural']);
    }

    /** @param string[]|int[]|float[] $parameters */
    public function translateSingular(
        string|Translatable $message,
        array $parameters = [],
        string $domain = BaseTranslator::DEFAULT_TEXT_DOMAIN,
        string|null $locale = null,
    ): string {
        if ($message instanceof Translatable) {
            $message = $message->translatableName();
        }

        return $this->translator->translate($message, $parameters, $domain, $locale);
    }

    /** @param string[]|int[]|float[] $parameters */
    public function translatePlural(
        string|Translatable $singularMessage,
        string|Translatable $pluralMessage,
        int $count,
        array $parameters = [],
        string $domain = BaseTranslator::DEFAULT_TEXT_DOMAIN,
        string|null $locale = null,
    ): string {
        if ($singularMessage instanceof Translatable) {
            $singularMessage = $singularMessage->translatableName();
        }

        if ($pluralMessage instanceof Translatable) {
            $pluralMessage = $pluralMessage->translatableName();
        }

        return $this->translator->translatePlural(
            $singularMessage,
            $pluralMessage,
            $count,
            $parameters,
            $domain,
            $locale,
        );
    }
}
