<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Queue\Handler;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Tragwerk\Application\Queue\Handler\RunDeployEnvironment;

use function assert;

final readonly class RunDeployEnvironmentFactory
{
    public function __invoke(ContainerInterface $container): RunDeployEnvironment
    {
        $logger      = $container->get(LoggerInterface::class);
        $lockFactory = $container->get(LockFactory::class);

        assert($logger instanceof LoggerInterface);
        assert($lockFactory instanceof LockFactory);

        return new RunDeployEnvironment($logger, $lockFactory);
    }
}
