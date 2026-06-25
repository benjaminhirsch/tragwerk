<?php

declare(strict_types=1);

namespace Tragwerk\Application\Helper;

use Dflydev\FigCookies\FigRequestCookies;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function hash;
use function sprintf;

/**
 * Builds and reads the trusted-device cookie. The cookie carries a single
 * opaque random token; only its SHA-256 hash is ever stored server-side.
 */
final readonly class TrustedDeviceCookie
{
    public const string NAME = 'tragwerk-2fa-trust';

    public static function readToken(ServerRequestInterface $request): string|null
    {
        // Read from the raw Cookie header (FigRequestCookies) for parity with how
        // mezzio-session resolves its own cookie; getCookieParams() is not always
        // populated by the PSR-7 server request factory.
        $value = FigRequestCookies::get($request, self::NAME)->getValue();

        return $value !== null && $value !== '' ? $value : null;
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function withCookie(ResponseInterface $response, string $token, int $days): ResponseInterface
    {
        $maxAge = $days * 86400;

        return $response->withAddedHeader('Set-Cookie', sprintf(
            '%s=%s; Max-Age=%d; Path=/; HttpOnly; Secure; SameSite=Lax',
            self::NAME,
            $token,
            $maxAge,
        ));
    }

    public static function withClearedCookie(ResponseInterface $response): ResponseInterface
    {
        return $response->withAddedHeader('Set-Cookie', sprintf(
            '%s=; Max-Age=0; Path=/; HttpOnly; Secure; SameSite=Lax',
            self::NAME,
        ));
    }
}
