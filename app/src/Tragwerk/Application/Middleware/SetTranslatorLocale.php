<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware;

use Laminas\I18n\Translator\Translator;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Domain\Enum\Locale;

final readonly class SetTranslatorLocale implements MiddlewareInterface
{
    public function __construct(
        private Translator $translator,
    ) {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $oldLocale = $this->translator->getLocale();

        $locale = $request->getAttribute(Locale::class);
        // For the moment
        $locale = Locale::DE_DE;
        //if ($locale instanceof Locale) {
        $this->translator->setLocale($locale->getLocaleCode());
        //}

        try {
            $response = $handler->handle($request);
        } finally {
            $this->translator->setLocale($oldLocale);
        }

        return $response;
    }
}
