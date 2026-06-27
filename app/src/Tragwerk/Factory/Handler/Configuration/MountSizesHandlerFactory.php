<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Handler\Configuration;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Tragwerk\Application\Handler\Configuration\MountSizesHandler;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\ProjectConfigLoader;
use Tragwerk\Application\Service\VolumeSizeReader;

use function assert;

final readonly class MountSizesHandlerFactory
{
    public function __invoke(ContainerInterface $container): MountSizesHandler
    {
        $renderer     = $container->get(ResponseRenderer::class);
        $configLoader = $container->get(ProjectConfigLoader::class);
        $sizeReader   = $container->get(VolumeSizeReader::class);
        $cache        = $container->get('session-cache');

        assert($renderer instanceof ResponseRenderer);
        assert($configLoader instanceof ProjectConfigLoader);
        assert($sizeReader instanceof VolumeSizeReader);
        assert($cache instanceof CacheItemPoolInterface);

        return new MountSizesHandler($renderer, $configLoader, $sizeReader, $cache);
    }
}
