<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Service;

use Psr\Container\ContainerInterface;
use Tragwerk\Application\Service\Registration\RegistrationSettings;

use function assert;
use function is_array;
use function is_bool;

final class RegistrationSettingsFactory
{
    public function __invoke(ContainerInterface $container): RegistrationSettings
    {
        $config = $container->get('config');
        assert(is_array($config));

        $registration = $config['registration'] ?? null;
        $enabled      = is_array($registration) ? $registration['enabled'] ?? null : null;

        return new RegistrationSettings(is_bool($enabled) ? $enabled : true);
    }
}
