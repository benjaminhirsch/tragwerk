<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Logger;

use Monolog\Logger;
use Psr\Container\ContainerInterface;

final class AppLoggerFactory extends LoggerFactory
{
    public function __invoke(ContainerInterface $container): Logger
    {
        return $this->createLogger('app', $container);
    }
}
