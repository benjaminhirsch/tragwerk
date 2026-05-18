<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Support;

use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

use function getenv;

final class TestDatabaseSetup
{
    private static bool $initialized = false;

    public static function ensure(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        self::createDatabaseIfAbsent();
        self::resetSchema();
        self::runMigrations();
    }

    /** @return array{driver: 'pdo_pgsql', host: string, port: int, dbname: string, user: string, password: string} */
    public static function connectionParams(): array
    {
        return [
            'driver'   => 'pdo_pgsql',
            'host'     => (string) (getenv('TEST_DB_HOST') ?: 'db'),
            'port'     => (int) (getenv('TEST_DB_PORT') ?: 5432),
            'dbname'   => (string) (getenv('TEST_DB_NAME') ?: 'app_test'),
            'user'     => (string) (getenv('TEST_DB_USER') ?: 'app'),
            'password' => (string) (getenv('TEST_DB_PASSWORD') ?: 'app'),
        ];
    }

    private static function createDatabaseIfAbsent(): void
    {
        $params = self::connectionParams();
        $admin  = DriverManager::getConnection([...$params, 'dbname' => 'postgres']);

        $exists = (bool) $admin
            ->executeQuery('SELECT 1 FROM pg_database WHERE datname = ?', [$params['dbname']])
            ->fetchOne();

        if (! $exists) {
            $admin->executeStatement('CREATE DATABASE "' . $params['dbname'] . '"');
        }

        $admin->close();
    }

    private static function resetSchema(): void
    {
        $connection = DriverManager::getConnection(self::connectionParams());
        $connection->executeStatement('DROP SCHEMA public CASCADE');
        $connection->executeStatement('CREATE SCHEMA public');
        $connection->close();
    }

    private static function runMigrations(): void
    {
        $connection = DriverManager::getConnection(self::connectionParams());

        $migrationsConfig = new Configuration();
        $migrationsConfig->addMigrationsDirectory(
            'Tragwerk\Infrastructure\Database\Migrations',
            __DIR__ . '/../../../src/Tragwerk/Infrastructure/Database/Migrations',
        );
        $migrationsConfig->setAllOrNothing(true);
        $migrationsConfig->setTransactional(true);

        $storageConfig = new TableMetadataStorageConfiguration();
        $storageConfig->setTableName('migrations');
        $migrationsConfig->setMetadataStorageConfiguration($storageConfig);

        $dependencyFactory = DependencyFactory::fromConnection(
            new ExistingConfiguration($migrationsConfig),
            new ExistingConnection($connection),
        );

        $cli = new ConsoleApplication();
        $cli->setAutoExit(false);
        $cli->addCommand(new MigrateCommand($dependencyFactory));
        $cli->run(
            new ArrayInput(['command' => 'migrations:migrate', '--no-interaction' => true]),
            new NullOutput(),
        );

        $connection->close();
    }
}
