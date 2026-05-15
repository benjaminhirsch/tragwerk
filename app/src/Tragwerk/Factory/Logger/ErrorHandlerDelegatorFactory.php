<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Logger;

use Laminas\Stratigility\Middleware\ErrorHandler;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Tragwerk\Application\Logger\LoggingErrorListener;

use function assert;

final readonly class ErrorHandlerDelegatorFactory
{
    /**
     * @param mixed[]|null $options
     *
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function __invoke(
        ContainerInterface $container,
        string $name,
        callable $callback,
        array|null $options = null,
    ): ErrorHandler {
        $handler = $callback();
        assert($handler instanceof ErrorHandler);

        $listener = $container->get(LoggingErrorListener::class);
        assert($listener instanceof LoggingErrorListener);

        $handler->attachListener($listener);

        return $handler;
    }
}
