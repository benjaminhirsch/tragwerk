<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Handler\Log;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Tragwerk\Application\Handler\Log\TailHandler;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ServerRepository;

use function assert;

final readonly class TailHandlerFactory
{
    public function __invoke(ContainerInterface $container): TailHandler
    {
        $renderer    = $container->get(ResponseRenderer::class);
        $servers     = $container->get(ServerRepository::class);
        $credentials = $container->get(CredentialRepository::class);
        $cache       = $container->get('session-cache');

        assert($renderer instanceof ResponseRenderer);
        assert($servers instanceof ServerRepository);
        assert($credentials instanceof CredentialRepository);
        assert($cache instanceof CacheItemPoolInterface);

        return new TailHandler($renderer, $servers, $credentials, $cache);
    }
}
