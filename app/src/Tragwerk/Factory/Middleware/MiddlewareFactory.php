<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Middleware;

use Laminas\Stratigility\Middleware\CallableMiddlewareDecorator;
use Laminas\Stratigility\Middleware\RequestHandlerMiddleware;
use Laminas\Stratigility\MiddlewarePipe;
use Mezzio\Exception;
use Mezzio\Middleware;
use Mezzio\MiddlewareContainer;
use Mezzio\MiddlewareFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_shift;
use function count;
use function is_array;
use function is_callable;
use function is_string;

final readonly class MiddlewareFactory implements MiddlewareFactoryInterface
{
    public function __construct(private readonly MiddlewareContainer $container)
    {
    }

    /** @inheritDoc */
    public function prepare($middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if ($middleware instanceof RequestHandlerInterface) {
            return $this->handler($middleware);
        }

        if (is_callable($middleware)) {
            return $this->callable($middleware);
        }

        if (is_array($middleware)) {
            return $this->pipeline(...$middleware);
        }

        /** @psalm-suppress DocblockTypeContradiction Unless there are native types, we should not trust phpdoc */
        if (! is_string($middleware) || $middleware === '') {
            throw Exception\InvalidMiddlewareException::forMiddleware($middleware);
        }

        return $this->lazy($middleware);
    }

    public function callable(callable $middleware): CallableMiddlewareDecorator
    {
        return new CallableMiddlewareDecorator($middleware);
    }

    public function handler(RequestHandlerInterface $handler): RequestHandlerMiddleware
    {
        return new RequestHandlerMiddleware($handler);
    }

    public function lazy(string $middleware): Middleware\LazyLoadingMiddleware
    {
        return new Middleware\LazyLoadingMiddleware($this->container, $middleware);
    }

    /** @inheritDoc */
    public function pipeline(...$middleware): MiddlewarePipe
    {
        // Allow passing arrays of middleware or individual lists of middleware
        if (
            is_array($middleware[0])
            && count($middleware) === 1
        ) {
            $middleware = array_shift($middleware);
        }

        $pipeline = new MiddlewarePipe();
        // @phpstan-ignore foreach.nonIterable
        foreach ($middleware as $m) {
            $pipeline->pipe($this->prepare($m));
        }

        return $pipeline;
    }
}
