<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Mail\Transport;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Tragwerk\Factory\Exception\MissingConfiguration;

use function assert;
use function is_array;
use function is_string;

final readonly class SendmailFactory
{
    public function __invoke(ContainerInterface $container): SendmailTransport
    {
        $config = $container->get('config');
        assert(is_array($config));

        $transportConfig = $config['mail']['transports'][SendmailTransport::class] ?? null;
        if ($transportConfig === null) {
            throw MissingConfiguration::createFromSubject('transport configuration entry');
        }

        if ($transportConfig['command'] === '' || $transportConfig['command'] === null) {
            throw MissingConfiguration::createFromSubject('sendmail command');
        }

        $dispatcher = $container->get(EventDispatcherInterface::class);
        assert($dispatcher instanceof EventDispatcherInterface);

        $logger = $container->get(LoggerInterface::class);
        assert($logger instanceof LoggerInterface);

        $command = $transportConfig['command'];
        assert(is_string($command));

        return new SendmailTransport(
            $command,
            $dispatcher,
            $logger,
        );
    }
}
