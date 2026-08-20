<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Git;

use Psr\Container\ContainerInterface;
use Tragwerk\Infrastructure\Git\BareRepository;

use function assert;
use function is_array;
use function is_int;
use function is_string;

final readonly class BareRepositoryFactory
{
    public function __invoke(ContainerInterface $container): BareRepository
    {
        $config = $container->get('config');
        assert(is_array($config));

        $repositoriesPath = $config['git']['repositories_path'] ?? 'data/repositories';
        $appInternalUrl   = $config['git']['app_internal_url'] ?? 'http://app';
        $repositoryUid    = $config['git']['repository_uid'] ?? 1000;
        $repositoryGid    = $config['git']['repository_gid'] ?? 1000;
        assert(is_string($repositoriesPath));
        assert(is_string($appInternalUrl));
        assert(is_int($repositoryUid));
        assert(is_int($repositoryGid));

        return new BareRepository($repositoriesPath, $appInternalUrl, $repositoryUid, $repositoryGid);
    }
}
