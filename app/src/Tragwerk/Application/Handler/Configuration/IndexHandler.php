<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Configuration;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\ProjectConfigLoader;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Repository\CronRunRepository;
use Tragwerk\Domain\Repository\DomainRepository;
use Tragwerk\Domain\Repository\EnvironmentStateRepository;
use Tragwerk\Domain\Service\DomainResolver;

use function assert;
use function is_string;

final readonly class IndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectConfigLoader $configLoader,
        private DomainRepository $domainRepository,
        private DomainResolver $domainResolver,
        private CronRunRepository $cronRunRepository,
        private EnvironmentStateRepository $environmentStateRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $activeProject = $request->getAttribute('active_project');
        assert($activeProject instanceof Project);

        $activeBranch = $request->getAttribute('active_environment');
        assert(is_string($activeBranch));

        $projectConfig = $this->configLoader->load($activeProject->id, $activeBranch);
        $domains       = $this->domainRepository->findByProject($activeProject->id);

        // Resolve each route placeholder to its effective host(s) for the active
        // environment, applying wildcard subdomain derivation the same way the
        // deploy pipeline does (DomainResolver).
        $hostsByPlaceholder = $this->domainResolver->resolveForEnvironment($domains, $activeBranch);

        // Latest cron run per job (keyed by command) so the cron table can show last-run status.
        $cronRuns = $this->cronRunRepository->latestPerJob($activeProject->id, $activeBranch);

        return $this->renderer->render($request, 'page::configuration/index', [
            'project'            => $activeProject,
            'branch'             => $activeBranch,
            'projectConfig'      => $projectConfig,
            'hostsByPlaceholder' => $hostsByPlaceholder,
            'cronRuns'           => $cronRuns,
            'disabled'           => $this->environmentStateRepository->isDisabled($activeProject->id, $activeBranch),
        ]);
    }
}
