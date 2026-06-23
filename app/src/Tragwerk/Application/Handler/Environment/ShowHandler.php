<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Environment;

use ArrayIterator;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Helper\ListHelper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\BranchAncestorResolver;
use Tragwerk\Domain\Entity\Domain;
use Tragwerk\Domain\Entity\EnvVar;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\Repository\DomainRepository;
use Tragwerk\Domain\Repository\EnvVarRepository;

use function is_string;
use function iterator_to_array;

final readonly class ShowHandler implements RequestHandlerInterface
{
    private const int DEPLOYMENTS_LIMIT = 10;

    public function __construct(
        private ResponseRenderer $renderer,
        private DeployJobRepository $deployJobRepository,
        private DomainRepository $domainRepository,
        private EnvVarRepository $envVarRepository,
        private BranchAncestorResolver $branchAncestorResolver,
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
                'branch'        => null,
                'deployments'   => [],
                'primaryDomain' => null,
                'vars'          => [],
            ]);
        }

        $deployments = $this->deployJobRepository->getPagedByProjectAndBranch(
            $project->id,
            $branch,
            self::DEPLOYMENTS_LIMIT,
            0,
        );

        $domains       = $this->domainRepository->findByEnvironment($project->id, $branch);
        $primaryDomain = null;
        foreach ($domains as $domain) {
            if ($domain->isPrimary) {
                $primaryDomain = $domain;
                break;
            }
        }

        $ancestors     = $this->branchAncestorResolver->getAncestors($project->id->toString(), $branch);
        $branchVars    = $this->envVarRepository->findByBranch($project->id, $branch);
        $inheritedVars = $this->envVarRepository->findInheritedFromAncestors($project->id, $ancestors);

        /** @var list<EnvVar> $vars */
        $vars = [
            ...iterator_to_array($branchVars, false),
            ...iterator_to_array($inheritedVars, false),
        ];

        return $this->renderer->render($request, 'page::environment/show', [
            'branch'        => $branch,
            'deployments'   => $deployments,
            'primaryDomain' => $primaryDomain instanceof Domain ? $primaryDomain : null,
            'vars'          => iterator_to_array(ListHelper::sort(new ArrayIterator($vars), 'key'), false),
        ]);
    }
}
