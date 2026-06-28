<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Handler\Project;

use Mezzio\Helper\UrlHelper;
use Psr\Container\ContainerInterface;
use Tragwerk\Application\Handler\Project\ShowHandler;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\Repository\DomainRepository;
use Tragwerk\Domain\Repository\EnvironmentStateRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Service\DomainResolver;
use Tragwerk\Infrastructure\Git\BareRepository;

use function assert;
use function is_array;
use function is_string;

final readonly class ShowHandlerFactory
{
    public function __invoke(ContainerInterface $container): ShowHandler
    {
        $config = $container->get('config');
        assert(is_array($config));

        $sshHost     = $config['git']['ssh_host'] ?? 'localhost';
        $sshRepoBase = $config['git']['ssh_repo_base'] ?? 'repos';
        assert(is_string($sshHost));
        assert(is_string($sshRepoBase));

        $renderer   = $container->get(ResponseRenderer::class);
        $projects   = $container->get(ProjectRepository::class);
        $servers    = $container->get(ServerRepository::class);
        $registries = $container->get(RegistryRepository::class);
        $deployJobs = $container->get(DeployJobRepository::class);
        $domains    = $container->get(DomainRepository::class);
        $resolver   = $container->get(DomainResolver::class);
        $bare       = $container->get(BareRepository::class);
        $envState   = $container->get(EnvironmentStateRepository::class);
        $urlHelper  = $container->get(UrlHelper::class);

        assert($renderer instanceof ResponseRenderer);
        assert($projects instanceof ProjectRepository);
        assert($servers instanceof ServerRepository);
        assert($registries instanceof RegistryRepository);
        assert($deployJobs instanceof DeployJobRepository);
        assert($domains instanceof DomainRepository);
        assert($resolver instanceof DomainResolver);
        assert($bare instanceof BareRepository);
        assert($envState instanceof EnvironmentStateRepository);
        assert($urlHelper instanceof UrlHelper);

        return new ShowHandler(
            $renderer,
            $projects,
            $servers,
            $registries,
            $deployJobs,
            $domains,
            $resolver,
            $bare,
            $envState,
            $urlHelper,
            $sshHost,
            $sshRepoBase,
        );
    }
}
