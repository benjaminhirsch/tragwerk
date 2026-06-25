<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Middleware;

use Mezzio\Authentication\UserInterface;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Middleware\NegotiateLocale;
use Tragwerk\Domain\Enum\Locale;

#[AllowMockObjectsWithoutExpectations]
final class NegotiateLocaleTest extends TestCase
{
    private NegotiateLocale $middleware;

    protected function setUp(): void
    {
        $this->middleware = new NegotiateLocale();
    }

    #[Test]
    public function sessionOverrideWinsOverEverything(): void
    {
        $request = $this->buildRequest(
            sessionLocale: 'en_US',
            userLocale: 'de_DE',
            acceptLanguage: 'de',
        );

        self::assertSame(Locale::EN_US, $this->negotiate($request));
    }

    #[Test]
    public function persistedUserPreferenceIsUsed(): void
    {
        $request = $this->buildRequest(
            sessionLocale: null,
            userLocale: 'de_DE',
            acceptLanguage: 'en',
        );

        self::assertSame(Locale::DE_DE, $this->negotiate($request));
    }

    #[Test]
    public function browserLanguageIsNegotiatedWhenNoPreference(): void
    {
        $request = $this->buildRequest(
            sessionLocale: null,
            userLocale: null,
            acceptLanguage: 'de-DE,de;q=0.9',
        );

        self::assertSame(Locale::DE_DE, $this->negotiate($request));
    }

    #[Test]
    public function unsupportedBrowserLanguageFallsBackToEnglish(): void
    {
        $request = $this->buildRequest(
            sessionLocale: null,
            userLocale: null,
            acceptLanguage: 'fr-FR,fr;q=0.9',
        );

        self::assertSame(Locale::EN_US, $this->negotiate($request));
    }

    #[Test]
    public function defaultsToEnglishWithoutAnySignal(): void
    {
        $request = $this->buildRequest(
            sessionLocale: null,
            userLocale: null,
            acceptLanguage: '',
        );

        self::assertSame(Locale::EN_US, $this->negotiate($request));
    }

    private function negotiate(ServerRequestInterface $request): Locale
    {
        $captured = null;
        $handler  = $this->handlerCapturing($captured);

        $this->middleware->process($request, $handler);

        self::assertNotNull($captured);
        $locale = $captured->getAttribute(Locale::class);
        self::assertInstanceOf(Locale::class, $locale);

        return $locale;
    }

    private function buildRequest(
        string|null $sessionLocale,
        string|null $userLocale,
        string $acceptLanguage,
    ): ServerRequestInterface {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('locale')->willReturn($sessionLocale);

        $request = new Psr17Factory()->createServerRequest('GET', '/')
            ->withAttribute(SessionMiddleware::SESSION_ATTRIBUTE, $session);

        if ($acceptLanguage !== '') {
            $request = $request->withHeader('Accept-Language', $acceptLanguage);
        }

        if ($userLocale !== null) {
            $user = $this->createMock(UserInterface::class);
            $user->method('getDetail')->with('locale')->willReturn($userLocale);
            $request = $request->withAttribute(UserInterface::class, $user);
        }

        return $request;
    }

    private function handlerCapturing(ServerRequestInterface|null &$captured): RequestHandlerInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $handler  = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(
            static function (ServerRequestInterface $request) use (&$captured, $response): ResponseInterface {
                $captured = $request;

                return $response;
            },
        );

        return $handler;
    }
}
