<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Queue\Handler;

use CuyZ\Valinor\Mapper\TreeMapper;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Tragwerk\Application\Queue\Handler\BuildEnvironment;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Domain\Config\XmlToArrayConverter;
use Tragwerk\Domain\Docker\DockerComposeGenerator;
use Tragwerk\Domain\Docker\DockerfileGenerator;
use Tragwerk\Domain\Repository\DomainRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Infrastructure\Git\BareRepository;

use function assert;
use function is_array;
use function is_string;

final readonly class BuildEnvironmentFactory
{
    public function __invoke(ContainerInterface $container): BuildEnvironment
    {
        $config = $container->get('config');
        assert(is_array($config));

        $dataPath = $config['project']['data_path'] ?? 'data/project';
        assert(is_string($dataPath));

        $bareRepository   = $container->get(BareRepository::class);
        $xmlConverter     = $container->get(XmlToArrayConverter::class);
        $treeMapper       = $container->get(TreeMapper::class);
        $composeGenerator = $container->get(DockerComposeGenerator::class);
        $dockerGenerator  = $container->get(DockerfileGenerator::class);
        $dispatcher       = $container->get(EventDispatcherInterface::class);
        $logger           = $container->get(LoggerInterface::class);
        $producer         = $container->get(Producer::class);
        $domains          = $container->get(DomainRepository::class);
        $projects         = $container->get(ProjectRepository::class);
        $teams            = $container->get(TeamRepository::class);
        $users            = $container->get(UserRepository::class);
        $lockFactory      = $container->get(LockFactory::class);

        assert($bareRepository instanceof BareRepository);
        assert($xmlConverter instanceof XmlToArrayConverter);
        assert($treeMapper instanceof TreeMapper);
        assert($composeGenerator instanceof DockerComposeGenerator);
        assert($dockerGenerator instanceof DockerfileGenerator);
        assert($dispatcher instanceof EventDispatcherInterface);
        assert($logger instanceof LoggerInterface);
        assert($producer instanceof Producer);
        assert($domains instanceof DomainRepository);
        assert($projects instanceof ProjectRepository);
        assert($teams instanceof TeamRepository);
        assert($users instanceof UserRepository);
        assert($lockFactory instanceof LockFactory);

        return new BuildEnvironment(
            $bareRepository,
            $xmlConverter,
            $treeMapper,
            $composeGenerator,
            $dockerGenerator,
            $dispatcher,
            $logger,
            $dataPath,
            $producer,
            $domains,
            $projects,
            $teams,
            $users,
            $lockFactory,
        );
    }
}
