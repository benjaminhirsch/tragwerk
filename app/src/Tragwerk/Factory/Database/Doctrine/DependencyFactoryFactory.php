<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Database\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

use function assert;

final readonly class DependencyFactoryFactory
{
    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): DependencyFactory
    {
        $configuration = $container->get(Configuration::class);
        $connection    = $container->get(Connection::class);
        $logger        = $container->get(LoggerInterface::class);

        assert($configuration instanceof Configuration);
        assert($connection instanceof Connection);
        assert($logger instanceof LoggerInterface);

        return DependencyFactory::fromConnection(
            new ExistingConfiguration($configuration),
            new ExistingConnection($connection),
            $logger,
        );
    }
}
