<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Handler\Project;

use Psr\Container\ContainerInterface;
use Tragwerk\Application\Handler\Project\TabHandler;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ProjectWebhookRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Repository\TeamRepository;

use function assert;
use function is_array;
use function is_string;

final readonly class TabHandlerFactory
{
    public function __invoke(ContainerInterface $container): TabHandler
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
        $teams      = $container->get(TeamRepository::class);
        $registries = $container->get(RegistryRepository::class);
        $webhooks   = $container->get(ProjectWebhookRepository::class);

        assert($renderer instanceof ResponseRenderer);
        assert($projects instanceof ProjectRepository);
        assert($servers instanceof ServerRepository);
        assert($teams instanceof TeamRepository);
        assert($registries instanceof RegistryRepository);
        assert($webhooks instanceof ProjectWebhookRepository);

        return new TabHandler($renderer, $projects, $servers, $teams, $registries, $webhooks, $sshHost, $sshRepoBase);
    }
}
