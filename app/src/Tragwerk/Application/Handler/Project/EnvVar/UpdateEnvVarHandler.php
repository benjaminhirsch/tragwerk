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
use Tragwerk\Domain\Entity\EnvVar;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Event\EnvVarUpdated;
use Tragwerk\Domain\Repository\EnvVarRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\EnvVarIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function assert;
use function is_array;
use function is_string;

final readonly class UpdateEnvVarHandler implements RequestHandlerInterface
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
            $existing = $this->envVarRepository->getById(EnvVarIdentifier::fromString($varId));
        } catch (Throwable) {
            return new EmptyResponse(404);
        }

        if ($existing->projectId->toString() !== $project->id->toString()) {
            return new EmptyResponse(403);
        }

        $body        = $request->getParsedBody();
        $newValue    = is_array($body) && is_string($body['value'] ?? null) ? $body['value'] : '';
        $isSecret    = is_array($body) && isset($body['is_secret']);
        $isInherited = is_array($body) && isset($body['is_inherited']);

        // When secret and value is blank, keep existing value
        $value = $existing->isSecret && $newValue === '' ? $existing->value : $newValue;

        $updated = new EnvVar(
            id:          $existing->id,
            projectId:   $existing->projectId,
            branch:      $existing->branch,
            key:         $existing->key,
            value:       $value,
            isSecret:    $isSecret,
            isInherited: $isInherited,
            createdAt:   $existing->createdAt,
            updatedAt:   TimestampImmutable::now(),
        );

        $this->eventDispatcher->dispatch(new EnvVarUpdated($updated));

        return $this->renderList($request, $project, $branch, null);
    }

    private function renderList(
        ServerRequestInterface $request,
        Project $project,
        string $branch,
        string|null $error,
    ): ResponseInterface {
        $ancestors     = $this->ancestorResolver->getAncestors($project->id->toString(), $branch);
        $ownVars       = $this->envVarRepository->findByBranch($project->id, $branch);
        $inheritedVars = $this->envVarRepository->findInheritedFromAncestors($project->id, $ancestors);

        return $this->renderer->render($request, 'partial::project/env-var-list', [
            'project'       => $project,
            'branch'        => $branch,
            'ownVars'       => $ownVars,
            'inheritedVars' => $inheritedVars,
            'error'         => $error,
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
