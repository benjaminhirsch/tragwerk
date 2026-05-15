<?php

declare(strict_types=1);

namespace Tragwerk\Application\Template\Extension;

use League\Plates\Engine;
use League\Plates\Extension\ExtensionInterface;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

use function assert;

final class Csrf implements ExtensionInterface, MiddlewareInterface
{
    private SessionInterface|null $session = null;

    public function __construct(
        private readonly \Tragwerk\Application\Service\Csrf $csrfService,
    ) {
    }

    #[Override]
    public function register(Engine $engine): void
    {
        $engine->registerFunction('getCsrfToken', [$this, 'getCsrfToken']);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        assert($session instanceof SessionInterface);
        $this->session = $session;

        try {
            $response = $handler->handle($request);
        } finally {
            $this->session = null;
        }

        return $response;
    }

    public function getCsrfToken(): string
    {
        if ($this->session === null) {
            throw new RuntimeException('Unable to generate CSRF Token: Session not loaded');
        }

        return $this->csrfService->generateToken($this->session);
    }
}
