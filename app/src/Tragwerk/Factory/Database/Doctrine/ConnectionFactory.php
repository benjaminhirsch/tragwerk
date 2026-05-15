<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Database\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Psr\Container\ContainerInterface;
use Tragwerk\Factory\Exception\MissingConfiguration;

use function assert;
use function is_array;
use function is_int;
use function is_string;

final readonly class ConnectionFactory
{
    public function __invoke(ContainerInterface $container): Connection
    {
        $config = $container->get('config');
        assert(is_array($config));

        $databaseConfig = $config['database']['default'] ?? [];
        assert(is_array($databaseConfig));

        $host     = $databaseConfig['host'] ?? null;
        $port     = $databaseConfig['port'] ?? null;
        $database = $databaseConfig['database'] ?? null;
        $username = $databaseConfig['username'] ?? null;
        $password = $databaseConfig['password'] ?? null;

        if (
            ! is_string($host)
            || ! is_int($port)
            || ! is_string($database)
            || ! is_string($username)
            || ! is_string($password)
        ) {
            throw MissingConfiguration::createFromSubject('database');
        }

        return DriverManager::getConnection([
            'driver'   => 'pdo_pgsql',
            'host'     => $host,
            'port'     => $port,
            'dbname'   => $database,
            'user'     => $username,
            'password' => $password,
        ]);
    }
}
