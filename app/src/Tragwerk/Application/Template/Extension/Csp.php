<?php

declare(strict_types=1);

namespace Tragwerk\Application\Template\Extension;

use League\Plates\Engine;
use League\Plates\Extension\ExtensionInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function base64_encode;
use function implode;
use function random_bytes;
use function sprintf;

/**
 * Sets a strict Content-Security-Policy that only permits same-origin
 * resources. Inline <script>/<style> elements are allowed solely via a
 * per-request nonce exposed to templates through cspNonce().
 *
 * Note: a nonce cannot whitelist inline style="..." attributes (the CSP spec
 * only honours nonces on <script>/<style> elements). Those are permitted via
 * the separate style-src-attr 'unsafe-inline' directive instead.
 */
final class Csp implements MiddlewareInterface, ExtensionInterface
{
    private string|null $nonce = null;

    #[Override]
    public function register(Engine $engine): void
    {
        $engine->registerFunction('cspNonce', [$this, 'cspNonce']);
    }

    public function cspNonce(): string
    {
        // The middleware always runs before templates render, so this is set.
        return $this->nonce ?? '';
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->nonce = base64_encode(random_bytes(16));

        try {
            $response = $handler->handle($request);
        } finally {
            $nonce       = $this->nonce;
            $this->nonce = null;
        }

        return $response
            ->withHeader('Content-Security-Policy', $this->buildPolicy($nonce))
            ->withHeader('X-Frame-Options', 'DENY');
    }

    private function buildPolicy(string $nonce): string
    {
        $nonceSrc = sprintf("'nonce-%s'", $nonce);

        $directives = [
            'default-src'     => "'self'",
            'img-src'         => "'self' data:",
            'script-src'      => "'self' " . $nonceSrc,
            'style-src'       => "'self' " . $nonceSrc,
            // style="..." attributes cannot carry a nonce; allow them explicitly.
            'style-src-attr'  => "'unsafe-inline'",
            'font-src'        => "'self'",
            'media-src'       => "'self'",
            'manifest-src'    => "'self'",
            'form-action'     => "'self'",
            'connect-src'     => "'self'",
            'object-src'      => "'none'",
            'frame-src'       => "'none'",
            'base-uri'        => "'self'",
            'frame-ancestors' => "'none'",
        ];

        $parts = [];
        foreach ($directives as $directive => $value) {
            $parts[] = $directive . ' ' . $value;
        }

        return implode('; ', $parts);
    }
}
