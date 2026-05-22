<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Cli;

use Doctrine\DBAL\Connection;
use Interop\Queue\Context;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Tragwerk\Application\Cli\Command\Worker;
use Tragwerk\Application\Queue\Processor\MessageProcessor;

use function assert;
use function is_array;
use function is_int;

final readonly class WorkerFactory
{
    public function __invoke(ContainerInterface $container): Worker
    {
        $config = $container->get('config');
        assert(is_array($config));
        $maxAttempts = $config['queue']['max_attempts'] ?? 5;
        assert(is_int($maxAttempts));

        $context    = $container->get(Context::class);
        $connection = $container->get(Connection::class);
        $logger     = $container->get(LoggerInterface::class);
        $processor  = $container->get(MessageProcessor::class);

        assert($context instanceof Context);
        assert($connection instanceof Connection);
        assert($logger instanceof LoggerInterface);
        assert($processor instanceof MessageProcessor);

        return new Worker($context, $connection, $logger, $processor, $maxAttempts);
    }
}
