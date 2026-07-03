<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Service;

use Psr\Container\ContainerInterface;
use Tragwerk\Application\Service\Credential\CredentialEncryptor;
use Tragwerk\Factory\Exception\MissingConfiguration;

use function assert;
use function is_array;
use function is_string;

final class CredentialEncryptorFactory
{
    public function __invoke(ContainerInterface $container): CredentialEncryptor
    {
        $config = $container->get('config');
        assert(is_array($config));

        $credential = $config['credential'] ?? null;
        if (! is_array($credential)) {
            throw MissingConfiguration::createFromSubject('credential configuration entry');
        }

        $encryptionKey = $credential['encryption_key'] ?? null;
        if (! is_string($encryptionKey) || $encryptionKey === '') {
            throw MissingConfiguration::createFromSubject('credential.encryption_key');
        }

        return new CredentialEncryptor($encryptionKey);
    }
}
