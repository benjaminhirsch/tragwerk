<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Cli;

use Psr\Container\ContainerInterface;
use Tragwerk\Application\Cli\Command\DeployEnvironmentCommand;
use Tragwerk\Domain\Repository\BuildLogRepository;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Infrastructure\Git\BareRepository;

use function assert;
use function is_array;
use function is_string;

final readonly class DeployEnvironmentCommandFactory
{
    public function __invoke(ContainerInterface $container): DeployEnvironmentCommand
    {
        $config = $container->get('config');
        assert(is_array($config));

        $dataPath = $config['project']['data_path'] ?? 'data/project';
        assert(is_string($dataPath));

        $projects    = $container->get(ProjectRepository::class);
        $servers     = $container->get(ServerRepository::class);
        $credentials = $container->get(CredentialRepository::class);
        $buildLogs   = $container->get(BuildLogRepository::class);
        $bareRepo    = $container->get(BareRepository::class);

        assert($projects instanceof ProjectRepository);
        assert($servers instanceof ServerRepository);
        assert($credentials instanceof CredentialRepository);
        assert($buildLogs instanceof BuildLogRepository);
        assert($bareRepo instanceof BareRepository);

        return new DeployEnvironmentCommand(
            $projects,
            $servers,
            $credentials,
            $buildLogs,
            $bareRepo,
            $dataPath,
        );
    }
}
