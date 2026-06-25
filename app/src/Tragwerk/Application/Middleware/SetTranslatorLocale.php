<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Translator\Translator;
use Tragwerk\Domain\Enum\Locale;

use function assert;

final readonly class SetTranslatorLocale implements MiddlewareInterface
{
    public function __construct(
        private Translator $translator,
    ) {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Must set the locale on the application Translator wrapper — the Plates
        // `t()` extension resolves through it. The underlying Laminas translator is a
        // separate instance, so calling setLocale() on it has no effect on templates.
        $oldLocale = $this->translator->getDefaultLocale();

        $locale = $request->getAttribute(Locale::class);
        assert($locale instanceof Locale);
        $this->translator->setDefaultLocale($locale->getLocaleCode());

        try {
            $response = $handler->handle($request);
        } finally {
            $this->translator->setDefaultLocale($oldLocale);
        }

        return $response;
    }
}
