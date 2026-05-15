<?php

declare(strict_types=1);

namespace Tragwerk\Factory\ErrorHandler\Whoops;

use Psr\Container\ContainerInterface;
use Whoops\Handler\PrettyPageHandler;

use function assert;

final readonly class PrettyPageHandlerDelegatorFactory
{
    /** @param mixed[]|null $options */
    public function __invoke(
        ContainerInterface $container,
        string $name,
        callable $callback,
        array|null $options = null,
    ): PrettyPageHandler {
        $handler = $callback();
        assert($handler instanceof PrettyPageHandler);

        $handler->handleUnconditionally(true);

        return $handler;
    }
}
