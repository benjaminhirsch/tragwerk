<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Git;

use Psr\Container\ContainerInterface;
use Tragwerk\Infrastructure\Git\BareRepository;

use function assert;
use function is_array;
use function is_string;

final readonly class BareRepositoryFactory
{
    public function __invoke(ContainerInterface $container): BareRepository
    {
        $config = $container->get('config');
        assert(is_array($config));

        $repositoriesPath = $config['git']['repositories_path'] ?? 'data/repositories';
        assert(is_string($repositoriesPath));

        return new BareRepository($repositoriesPath);
    }
}
