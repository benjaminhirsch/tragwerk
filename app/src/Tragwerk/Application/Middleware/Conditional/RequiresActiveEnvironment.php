<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware\Conditional;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function is_string;

final readonly class RequiresActiveEnvironment implements MiddlewareInterface
{
    public function __construct(
        private MiddlewareInterface $middleware,
    ) {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $activeEnvironment = $request->getAttribute('active_environment');
        if (! is_string($activeEnvironment)) {
            return $handler->handle($request);
        }

        return $this->middleware->process($request, $handler);
    }
}
