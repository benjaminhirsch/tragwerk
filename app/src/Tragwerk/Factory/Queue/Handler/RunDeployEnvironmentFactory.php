<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Queue\Handler;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Tragwerk\Application\Queue\Handler\RunDeployEnvironment;

use function assert;

final readonly class RunDeployEnvironmentFactory
{
    public function __invoke(ContainerInterface $container): RunDeployEnvironment
    {
        $logger = $container->get(LoggerInterface::class);
        assert($logger instanceof LoggerInterface);

        return new RunDeployEnvironment($logger);
    }
}
