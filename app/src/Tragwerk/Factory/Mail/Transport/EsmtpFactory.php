<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Mail\Transport;

use Psr\Container\ContainerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Tragwerk\Factory\Exception\MissingConfiguration;

use function assert;
use function is_array;
use function is_int;
use function is_string;

final readonly class EsmtpFactory
{
    public function __invoke(ContainerInterface $container): EsmtpTransport
    {
        $config = $container->get('config');
        assert(is_array($config));

        $transportConfig = $config['mail']['transports'][EsmtpTransport::class] ?? null;
        if ($transportConfig === null) {
            throw MissingConfiguration::createFromSubject('transport configuration entry');
        }

        $host = $transportConfig['host'];
        $port = $transportConfig['port'];
        assert(is_string($host) && is_int($port));
        $transport = new EsmtpTransport(
            $host,
            $port,
        );

        $localDomain = $transportConfig['localDomain'];
        assert(is_string($localDomain));
        $transport->setLocalDomain($localDomain);

        if ($transportConfig['username'] !== null) {
            $username = $transportConfig['username'];
            assert(is_string($username));
            $transport->setUsername($username);
        }

        if ($transportConfig['password'] !== null) {
            $password = $transportConfig['password'];
            assert(is_string($password));
            $transport->setPassword($password);
        }

        return $transport;
    }
}
