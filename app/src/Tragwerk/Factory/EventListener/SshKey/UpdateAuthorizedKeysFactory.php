<?php

declare(strict_types=1);

namespace Tragwerk\Factory\EventListener\SshKey;

use Psr\Container\ContainerInterface;
use Tragwerk\Application\EventListener\SshKey\UpdateAuthorizedKeys;
use Tragwerk\Domain\Repository\SshKeyRepository;

use function assert;
use function is_array;
use function is_string;

final readonly class UpdateAuthorizedKeysFactory
{
    public function __invoke(ContainerInterface $container): UpdateAuthorizedKeys
    {
        $config = $container->get('config');
        assert(is_array($config));

        $path = $config['git']['authorized_keys_path'] ?? 'data/ssh/authorized_keys';
        assert(is_string($path));

        $repository = $container->get(SshKeyRepository::class);
        assert($repository instanceof SshKeyRepository);

        return new UpdateAuthorizedKeys($repository, $path);
    }
}
