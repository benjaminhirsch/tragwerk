<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Queue\Dbal;

use Doctrine\DBAL\Connection;
use Enqueue\Dbal\DbalContext;
use Psr\Container\ContainerInterface;

final readonly class ContextFactory
{
    public function __invoke(ContainerInterface $container): DbalContext
    {
        return new DbalContext(
            static fn () => $container->get(Connection::class),
            [
                'table_name' => 'queue_messages',
                'subscription_polling_interval' => 1000, // milliseconds // 1 sec
                'polling_interval' => 1000, // milliseconds // 1 sec
                'redelivery_delay' => 900000, // milliseconds // 15 min
            ],
        );
    }
}
