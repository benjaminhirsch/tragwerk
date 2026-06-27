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
use Tragwerk\Domain\Repository\DomainRepository;

use function assert;
use function is_string;

final readonly class IndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectConfigLoader $configLoader,
        private DomainRepository $domainRepository,
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

        return $this->renderer->render($request, 'page::configuration/index', [
            'project'       => $activeProject,
            'branch'        => $activeBranch,
            'projectConfig' => $projectConfig,
            'domains'       => $domains,
        ]);
    }
}
