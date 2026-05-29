<?php

declare(strict_types=1);

namespace Tragwerk\Factory\EventListener\Project;

use Psr\Container\ContainerInterface;
use Tragwerk\Application\EventListener\Project\DeleteProjectData;

use function assert;
use function is_array;
use function is_string;

final readonly class DeleteProjectDataFactory
{
    public function __invoke(ContainerInterface $container): DeleteProjectData
    {
        $config = $container->get('config');
        assert(is_array($config));

        $dataPath = $config['project']['data_path'] ?? 'data/project';
        assert(is_string($dataPath));

        return new DeleteProjectData($dataPath);
    }
}
