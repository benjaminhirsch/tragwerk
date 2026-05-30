<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Queue\Handler;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Tragwerk\Application\Queue\Handler\RunSyncEnvironmentData;

use function assert;

final readonly class RunSyncEnvironmentDataFactory
{
    public function __invoke(ContainerInterface $container): RunSyncEnvironmentData
    {
        $logger      = $container->get(LoggerInterface::class);
        $lockFactory = $container->get(LockFactory::class);

        assert($logger instanceof LoggerInterface);
        assert($lockFactory instanceof LockFactory);

        return new RunSyncEnvironmentData($logger, $lockFactory);
    }
}
