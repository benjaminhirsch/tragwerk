<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware\Conditional;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_map;
use function in_array;
use function strtoupper;

final class Method implements MiddlewareInterface
{
    /** @param string[] $allowedMethods */
    public function __construct(
        private array $allowedMethods,
        private readonly MiddlewareInterface $middleware,
    ) {
        $this->allowedMethods = array_map('strtoupper', $this->allowedMethods);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $currentMethod = strtoupper($request->getMethod());

        $matchesMethod = in_array($currentMethod, $this->allowedMethods, true);
        if (! $matchesMethod) {
            return $handler->handle($request);
        }

        return $this->middleware->process($request, $handler);
    }
}
