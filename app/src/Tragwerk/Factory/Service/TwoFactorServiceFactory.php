<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Service;

use Psr\Container\ContainerInterface;
use Tragwerk\Application\Service\TwoFactor\TwoFactorService;
use Tragwerk\Factory\Exception\MissingConfiguration;

use function assert;
use function is_array;
use function is_int;
use function is_string;

final class TwoFactorServiceFactory
{
    public function __invoke(ContainerInterface $container): TwoFactorService
    {
        $config = $container->get('config');
        assert(is_array($config));

        $twoFactor = $config['two_factor'] ?? null;
        if (! is_array($twoFactor)) {
            throw MissingConfiguration::createFromSubject('two_factor configuration entry');
        }

        $encryptionKey = $twoFactor['encryption_key'] ?? null;
        if (! is_string($encryptionKey) || $encryptionKey === '') {
            throw MissingConfiguration::createFromSubject('two_factor.encryption_key');
        }

        $issuer            = is_string($twoFactor['issuer'] ?? null) ? $twoFactor['issuer'] : 'Tragwerk';
        $trustedDeviceDays = is_int($twoFactor['trusted_device_days'] ?? null)
            ? $twoFactor['trusted_device_days']
            : 30;

        return new TwoFactorService($encryptionKey, $issuer, $trustedDeviceDays);
    }
}
