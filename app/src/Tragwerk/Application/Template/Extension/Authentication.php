<?php

declare(strict_types=1);

namespace Tragwerk\Application\Template\Extension;

use League\Plates\Engine;
use League\Plates\Extension\ExtensionInterface;
use Mezzio\Authentication\UserInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function assert;

final class Authentication implements MiddlewareInterface, ExtensionInterface
{
    private UserInterface|null $authSession = null;

    #[Override]
    public function register(Engine $engine): void
    {
        $engine->registerFunction('isAuthenticated', [$this, 'isAuthenticated']);
        $engine->registerFunction('user', [$this, 'getAuthenticationSession']);
    }

    /** @psalm-mutation-free */
    public function getAuthenticationSession(): UserInterface|null
    {
        return $this->authSession;
    }

    /** @psalm-mutation-free */
    public function isAuthenticated(): bool
    {
        return $this->authSession !== null;
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authSession = $request->getAttribute(UserInterface::class);
        assert($authSession === null || $authSession instanceof UserInterface);
        $this->authSession = $authSession;

        try {
            $response = $handler->handle($request);
        } finally {
            $this->authSession = null;
        }

        return $response;
    }
}
