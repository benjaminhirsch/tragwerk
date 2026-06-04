<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Queue\Handler;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Tragwerk\Application\Queue\Handler\RunCleanupProjectDocker;

use function assert;

final readonly class RunCleanupProjectDockerFactory
{
    public function __invoke(ContainerInterface $container): RunCleanupProjectDocker
    {
        $logger = $container->get(LoggerInterface::class);
        assert($logger instanceof LoggerInterface);

        return new RunCleanupProjectDocker($logger);
    }
}
