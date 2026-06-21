<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Handler\Container;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Tragwerk\Application\Handler\Container\StatusHandler;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ServerRepository;

use function assert;

final readonly class StatusHandlerFactory
{
    public function __invoke(ContainerInterface $container): StatusHandler
    {
        $renderer    = $container->get(ResponseRenderer::class);
        $credentials = $container->get(CredentialRepository::class);
        $servers     = $container->get(ServerRepository::class);
        $cache       = $container->get('session-cache');

        assert($renderer instanceof ResponseRenderer);
        assert($credentials instanceof CredentialRepository);
        assert($servers instanceof ServerRepository);
        assert($cache instanceof CacheItemPoolInterface);

        return new StatusHandler($renderer, $credentials, $servers, $cache);
    }
}
