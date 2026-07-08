<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Cli;

use CuyZ\Valinor\Mapper\TreeMapper;
use Psr\Container\ContainerInterface;
use Tragwerk\Application\Cli\Command\DeployEnvironmentCommand;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Application\Service\Credential\CredentialEncryptor;
use Tragwerk\Application\Service\EnvVarResolver;
use Tragwerk\Domain\Config\XmlToArrayConverter;
use Tragwerk\Domain\Docker\DockerComposeGenerator;
use Tragwerk\Domain\Docker\ServiceImageResolver;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\Repository\DomainRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\RegistryPrefixRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Service\DomainResolver;
use Tragwerk\Infrastructure\Deploy\VolumeSyncService;
use Tragwerk\Infrastructure\Git\BareRepository;
use Tragwerk\Infrastructure\Mercure\MercurePublisher;

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

        $projects       = $container->get(ProjectRepository::class);
        $servers        = $container->get(ServerRepository::class);
        $credentials    = $container->get(CredentialRepository::class);
        $deployJobs     = $container->get(DeployJobRepository::class);
        $registries     = $container->get(RegistryRepository::class);
        $domains        = $container->get(DomainRepository::class);
        $domainResolver = $container->get(DomainResolver::class);
        $bareRepo       = $container->get(BareRepository::class);
        $xmlConv        = $container->get(XmlToArrayConverter::class);
        $mapper         = $container->get(TreeMapper::class);
        $compose        = $container->get(DockerComposeGenerator::class);
        $imgResolver    = $container->get(ServiceImageResolver::class);
        $producer       = $container->get(Producer::class);
        $regPrefixes    = $container->get(RegistryPrefixRepository::class);
        $envVars        = $container->get(EnvVarResolver::class);
        $mercure        = $container->get(MercurePublisher::class);
        $encryptor      = $container->get(CredentialEncryptor::class);
        $volumeSync     = $container->get(VolumeSyncService::class);

        assert($projects instanceof ProjectRepository);
        assert($servers instanceof ServerRepository);
        assert($credentials instanceof CredentialRepository);
        assert($deployJobs instanceof DeployJobRepository);
        assert($registries instanceof RegistryRepository);
        assert($domains instanceof DomainRepository);
        assert($domainResolver instanceof DomainResolver);
        assert($bareRepo instanceof BareRepository);
        assert($xmlConv instanceof XmlToArrayConverter);
        assert($mapper instanceof TreeMapper);
        assert($compose instanceof DockerComposeGenerator);
        assert($imgResolver instanceof ServiceImageResolver);
        assert($producer instanceof Producer);
        assert($regPrefixes instanceof RegistryPrefixRepository);
        assert($envVars instanceof EnvVarResolver);
        assert($mercure instanceof MercurePublisher);
        assert($encryptor instanceof CredentialEncryptor);
        assert($volumeSync instanceof VolumeSyncService);

        return new DeployEnvironmentCommand(
            $projects,
            $servers,
            $credentials,
            $deployJobs,
            $registries,
            $domains,
            $domainResolver,
            $bareRepo,
            $xmlConv,
            $mapper,
            $compose,
            $imgResolver,
            $producer,
            $regPrefixes,
            $envVars,
            $dataPath,
            $mercure,
            $encryptor,
            $volumeSync,
        );
    }
}
