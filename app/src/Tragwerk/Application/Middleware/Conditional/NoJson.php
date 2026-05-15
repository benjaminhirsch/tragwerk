<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware\Conditional;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function str_contains;

final class NoJson implements MiddlewareInterface
{
    public function __construct(
        private readonly MiddlewareInterface $middleware,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $contentType = $request->getHeaderLine('Content-Type');
        if (str_contains($contentType, 'application/json')) {
            return $handler->handle($request);
        }

        return $this->middleware->process($request, $handler);
    }
}
