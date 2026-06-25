<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware;

use Mezzio\Authentication\UserInterface;
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
use function is_string;

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
        // 1. Explicit override set this session when the user saved a language.
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        assert($session instanceof SessionInterface);
        $sessionLocale = $session->get('locale');
        if (is_string($sessionLocale)) {
            $locale = Locale::tryFrom($sessionLocale);
            if ($locale !== null) {
                return $locale;
            }
        }

        // 2. The authenticated user's persisted preference (from the auth details).
        $user = $request->getAttribute(UserInterface::class);
        if ($user instanceof UserInterface) {
            $userLocale = $user->getDetail('locale');
            if (is_string($userLocale)) {
                $locale = Locale::tryFrom($userLocale);
                if ($locale !== null) {
                    return $locale;
                }
            }
        }

        // 3. Browser-preferred language via Accept-Language.
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
