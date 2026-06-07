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
use Tragwerk\Domain\Event\EnvVarCreated;
use Tragwerk\Domain\Repository\EnvVarRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\EnvVarIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function assert;
use function is_array;
use function is_string;
use function preg_match;
use function trim;

final readonly class CreateEnvVarHandler implements RequestHandlerInterface
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

        $body        = $request->getParsedBody();
        $key         = is_array($body) && is_string($body['key'] ?? null) ? trim($body['key']) : '';
        $value       = is_array($body) && is_string($body['value'] ?? null) ? $body['value'] : '';
        $isSecret    = is_array($body) && isset($body['is_secret']);
        $isInherited = is_array($body) && isset($body['is_inherited']);

        $error = $this->validate($key, $value);

        if ($error === null) {
            $now = TimestampImmutable::now();
            $var = new EnvVar(
                id:          EnvVarIdentifier::create(),
                projectId:   $project->id,
                branch:      $branch,
                key:         $key,
                value:       $value,
                isSecret:    $isSecret,
                isInherited: $isInherited,
                createdAt:   $now,
                updatedAt:   $now,
            );

            try {
                $this->eventDispatcher->dispatch(new EnvVarCreated($var));
            } catch (Throwable) {
                $error = 'A variable with this key already exists for this environment.';
            }
        }

        return $this->renderList($request, $project, $branch, $error);
    }

    private function validate(string $key, string $value): string|null
    {
        if ($key === '') {
            return 'Key is required.';
        }

        if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) !== 1) {
            return 'Key must start with an uppercase letter and contain only'
                . ' uppercase letters, digits, and underscores.';
        }

        if ($value === '') {
            return 'Value is required.';
        }

        return null;
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
