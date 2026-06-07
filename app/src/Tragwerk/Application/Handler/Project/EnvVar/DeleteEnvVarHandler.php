<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project\EnvVar;

use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\BranchAncestorResolver;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Event\EnvVarDeleted;
use Tragwerk\Domain\Repository\EnvVarRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\EnvVarIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

use function assert;
use function is_string;

final readonly class DeleteEnvVarHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectRepository $projectRepository,
        private EnvVarRepository $envVarRepository,
        private EventDispatcherInterface $eventDispatcher,
        private BranchAncestorResolver $ancestorResolver,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $this->resolveProject($request);
        if (! $project instanceof Project) {
            return new EmptyResponse(404);
        }

        $branch = $request->getAttribute('branch');
        if (! is_string($branch) || $branch === '') {
            return new EmptyResponse(400);
        }

        $varId = $request->getAttribute('varId');
        if (! is_string($varId) || ! EnvVarIdentifier::isValid($varId)) {
            return new EmptyResponse(400);
        }

        try {
            $var = $this->envVarRepository->getById(EnvVarIdentifier::fromString($varId));
        } catch (Throwable) {
            return new EmptyResponse(404);
        }

        if ($var->projectId->toString() !== $project->id->toString()) {
            return new EmptyResponse(403);
        }

        $this->eventDispatcher->dispatch(new EnvVarDeleted(
            id:           $var->id,
            projectId:    $project->id,
            branch:       $var->branch,
            wasInherited: $var->isInherited,
        ));

        return $this->renderList($request, $project, $branch);
    }

    private function renderList(ServerRequestInterface $request, Project $project, string $branch): ResponseInterface
    {
        $ancestors     = $this->ancestorResolver->getAncestors($project->id->toString(), $branch);
        $ownVars       = $this->envVarRepository->findByBranch($project->id, $branch);
        $inheritedVars = $this->envVarRepository->findInheritedFromAncestors($project->id, $ancestors);

        return $this->renderer->render($request, 'partial::project/env-var-list', [
            'project'       => $project,
            'branch'        => $branch,
            'ownVars'       => $ownVars,
            'inheritedVars' => $inheritedVars,
            'error'         => null,
        ]);
    }

    private function resolveProject(ServerRequestInterface $request): Project|null
    {
        $routeId = $request->getAttribute('id');
        if (! is_string($routeId) || ! ProjectIdentifier::isValid($routeId)) {
            return null;
        }

        $activeTeam = $request->getAttribute('active_team');
        if (! $activeTeam instanceof Team) {
            return null;
        }

        try {
            $project = $this->projectRepository->getById(ProjectIdentifier::fromString($routeId));
            assert($project instanceof Project);

            return $project->teamId->toString() === $activeTeam->id->toString() ? $project : null;
        } catch (Throwable) {
            return null;
        }
    }
}
