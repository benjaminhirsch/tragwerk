<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Database\Doctrine;

use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Psr\Container\ContainerInterface;

final readonly class ConfigurationFactory
{
    public function __invoke(ContainerInterface $container): Configuration
    {
        $configuration = new Configuration();

        $configuration->addMigrationsDirectory(
            'Tragwerk\Infrastructure\Database\Migrations',
            'src/Tragwerk/Infrastructure/Database/Migrations',
        );
        $configuration->setAllOrNothing(true);
        $configuration->setTransactional(true);
        $configuration->setCheckDatabasePlatform(true);
        $configuration->setCustomTemplate('src/Tragwerk/Infrastructure/Database/MigrationTemplate.tpl');

        $storageConfiguration = new TableMetadataStorageConfiguration();
        $storageConfiguration->setTableName('migrations');
        $configuration->setMetadataStorageConfiguration($storageConfiguration);

        return $configuration;
    }
}
