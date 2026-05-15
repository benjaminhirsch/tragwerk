<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware\Conditional;

use Mezzio\Authentication\UserInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class Authenticated implements MiddlewareInterface
{
    public function __construct(
        private MiddlewareInterface $middleware,
    ) {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authSession     = $request->getAttribute(UserInterface::class);
        $isAuthenticated = $authSession instanceof UserInterface;
        if (! $isAuthenticated) {
            return $handler->handle($request);
        }

        return $this->middleware->process($request, $handler);
    }
}
