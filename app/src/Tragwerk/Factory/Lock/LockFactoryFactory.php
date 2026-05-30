<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Lock;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\DoctrineDbalPostgreSqlStore;

use function assert;

final class LockFactoryFactory
{
    public function __invoke(ContainerInterface $container): LockFactory
    {
        $db = $container->get(Connection::class);
        assert($db instanceof Connection);

        return new LockFactory(new DoctrineDbalPostgreSqlStore($db));
    }
}
