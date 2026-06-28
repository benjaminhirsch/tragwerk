<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Handler\Configuration;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Tragwerk\Application\Handler\Configuration\ServiceStatusHandler;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\ContainerStateReader;
use Tragwerk\Application\Service\ProjectConfigLoader;
use Tragwerk\Domain\Repository\EnvironmentStateRepository;

use function assert;

final readonly class ServiceStatusHandlerFactory
{
    public function __invoke(ContainerInterface $container): ServiceStatusHandler
    {
        $renderer     = $container->get(ResponseRenderer::class);
        $configLoader = $container->get(ProjectConfigLoader::class);
        $stateReader  = $container->get(ContainerStateReader::class);
        $envState     = $container->get(EnvironmentStateRepository::class);
        $cache        = $container->get('session-cache');

        assert($renderer instanceof ResponseRenderer);
        assert($configLoader instanceof ProjectConfigLoader);
        assert($stateReader instanceof ContainerStateReader);
        assert($envState instanceof EnvironmentStateRepository);
        assert($cache instanceof CacheItemPoolInterface);

        return new ServiceStatusHandler($renderer, $configLoader, $stateReader, $envState, $cache);
    }
}
