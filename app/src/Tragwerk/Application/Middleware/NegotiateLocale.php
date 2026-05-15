<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware;

use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Negotiation\AcceptLanguage;
use Negotiation\Exception\Exception;
use Negotiation\LanguageNegotiator;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Domain\Enum\Locale;

use function array_find;
use function assert;

class NegotiateLocale implements MiddlewareInterface
{
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $locale = $this->determineLocale($request);

        $request = $request->withAttribute(Locale::class, $locale);

        return $handler->handle($request);
    }

    private function determineLocale(ServerRequestInterface $request): Locale
    {
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        assert($session instanceof SessionInterface);
        $locale = $session->get('locale');
        if ($locale instanceof Locale) {
            return $locale;
        }

        $acceptLanguageHeader = $request->getHeaderLine('Accept-Language');
        if ($acceptLanguageHeader !== '') {
            $negotiatedLocale = $this->tryNegotiateLocale($acceptLanguageHeader);
            if ($negotiatedLocale !== null) {
                return $negotiatedLocale;
            }
        }

        return Locale::DEFAULT;
    }

    private function tryNegotiateLocale(string $acceptLanguageHeader): Locale|null
    {
        $availableLanguages = [];
        foreach (Locale::cases() as $locale) {
            $availableLanguages[] = $locale->getLocaleCode();
            $availableLanguages[] = $locale->getLanguageCode();
        }

        try {
            $result = new LanguageNegotiator()->getBest($acceptLanguageHeader, $availableLanguages);
        } catch (Exception) {
            return null;
        }

        if ($result === null) {
            return null;
        }

        assert($result instanceof AcceptLanguage);

        $matchedCode = $result->getValue();

        $matchedLocale = Locale::tryFrom($matchedCode);
        if ($matchedLocale !== null) {
            return $matchedLocale;
        }

        return array_find(Locale::cases(), static fn ($locale) => $locale->getLanguageCode() === $matchedCode);
    }
}
