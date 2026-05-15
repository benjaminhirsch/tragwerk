<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Support;

use Doctrine\DBAL\Connection;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;

use function assert;

abstract class IntegrationTestCase extends TestCase
{
    protected ServiceManager $container;
    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        TestDatabaseSetup::ensure();

        $this->container = $this->buildContainer();
        $connection      = $this->container->get(Connection::class);
        assert($connection instanceof Connection);
        $this->connection = $connection;
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();

        parent::tearDown();
    }

    private function buildContainer(): ServiceManager
    {
        // Load standard config, then override the database to point at app_test.
        // Config cache may exist from a dev run — that's fine, we override afterwards.
        $config = require __DIR__ . '/../../../config/config.php';

        $dbParams                      = TestDatabaseSetup::connectionParams();
        $config['database']['default'] = [
            'host'     => $dbParams['host'],
            'port'     => $dbParams['port'],
            'database' => $dbParams['dbname'],
            'username' => $dbParams['user'],
            'password' => $dbParams['password'],
        ];

        $dependencies                       = $config['dependencies'];
        $dependencies['services']['config'] = $config;

        /** @phpstan-ignore argument.type */
        return new ServiceManager($dependencies);
    }
}
