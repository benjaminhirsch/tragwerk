<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Mail\Transport;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Tragwerk\Factory\Exception\MissingConfiguration;

use function assert;
use function is_array;
use function is_string;

final readonly class TransportFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): TransportInterface
    {
        $config = $container->get('config');
        assert(is_array($config));

        $transportClass = $config['mail']['transport'] ?? null;
        if ($transportClass === null) {
            throw MissingConfiguration::createFromSubject('transport class entry');
        }

        assert(is_string($transportClass));
        $transport = $container->get($transportClass);
        assert($transport instanceof TransportInterface);

        return $transport;
    }
}
