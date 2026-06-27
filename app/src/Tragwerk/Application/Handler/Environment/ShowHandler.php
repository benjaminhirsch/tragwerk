<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Environment;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\Repository\DomainRepository;
use Tragwerk\Domain\Service\DomainResolver;

use function is_string;

final readonly class ShowHandler implements RequestHandlerInterface
{
    private const int DEPLOYMENTS_LIMIT = 10;

    public function __construct(
        private ResponseRenderer $renderer,
        private DeployJobRepository $deployJobRepository,
        private DomainRepository $domainRepository,
        private DomainResolver $domainResolver,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $request->getAttribute('active_project');
        $branch  = $request->getAttribute('active_environment');

        // The environment.show route is not guarded by RequiresActiveEnvironment, so the
        // context can legitimately be missing (no project selected, no branches yet).
        if (! $project instanceof Project || ! is_string($branch) || $branch === '') {
            return $this->renderer->render($request, 'page::environment/show', [
                'branch'      => null,
                'deployments' => [],
                'primaryHost' => null,
            ]);
        }

        $deployments = $this->deployJobRepository->getPagedByProjectAndBranch(
            $project->id,
            $branch,
            self::DEPLOYMENTS_LIMIT,
            0,
        );

        if (($request->getQueryParams()['fragment'] ?? null) === 'deployments') {
            return $this->renderer->render($request, 'page::environment/_deployments', ['deployments' => $deployments]);
        }

        $primaryHost = $this->domainResolver->primaryHost(
            $this->domainRepository->findByProject($project->id),
            $branch,
        );

        return $this->renderer->render($request, 'page::environment/show', [
            'branch'      => $branch,
            'deployments' => $deployments,
            'primaryHost' => $primaryHost,
        ]);
    }
}
