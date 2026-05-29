<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Handler\Project;

use Psr\Container\ContainerInterface;
use Tragwerk\Application\Handler\Project\DownloadBuildHandler;
use Tragwerk\Domain\Repository\ProjectRepository;

use function assert;
use function is_array;
use function is_string;

final readonly class DownloadBuildHandlerFactory
{
    public function __invoke(ContainerInterface $container): DownloadBuildHandler
    {
        $config = $container->get('config');
        assert(is_array($config));

        $dataPath = $config['project']['data_path'] ?? 'data/project';
        assert(is_string($dataPath));

        $projects = $container->get(ProjectRepository::class);

        assert($projects instanceof ProjectRepository);

        return new DownloadBuildHandler($projects, $dataPath);
    }
}
