<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Cli;

use CuyZ\Valinor\Mapper\TreeMapper;
use Psr\Container\ContainerInterface;
use Tragwerk\Application\Cli\Command\SyncEnvironmentDataCommand;
use Tragwerk\Domain\Config\XmlToArrayConverter;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Infrastructure\Git\BareRepository;

use function assert;

final readonly class SyncEnvironmentDataCommandFactory
{
    public function __invoke(ContainerInterface $container): SyncEnvironmentDataCommand
    {
        $projects    = $container->get(ProjectRepository::class);
        $servers     = $container->get(ServerRepository::class);
        $credentials = $container->get(CredentialRepository::class);
        $deployJobs  = $container->get(DeployJobRepository::class);
        $bareRepo    = $container->get(BareRepository::class);
        $xmlConv     = $container->get(XmlToArrayConverter::class);
        $mapper      = $container->get(TreeMapper::class);

        assert($projects instanceof ProjectRepository);
        assert($servers instanceof ServerRepository);
        assert($credentials instanceof CredentialRepository);
        assert($deployJobs instanceof DeployJobRepository);
        assert($bareRepo instanceof BareRepository);
        assert($xmlConv instanceof XmlToArrayConverter);
        assert($mapper instanceof TreeMapper);

        return new SyncEnvironmentDataCommand(
            $projects,
            $servers,
            $credentials,
            $deployJobs,
            $bareRepo,
            $xmlConv,
            $mapper,
        );
    }
}
