<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project;

use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Event\BranchActivated;
use Tragwerk\Domain\Event\BranchDeactivated;
use Tragwerk\Domain\Repository\EnvironmentRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

use function in_array;
use function is_array;
use function is_string;

final readonly class ToggleBranchHandler implements RequestHandlerInterface
{
    private const array PROTECTED_BRANCHES = ['main', 'master'];

    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectRepository $projectRepository,
        private EnvironmentRepository $environmentRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $this->resolveProject($request);
        if (! $project instanceof Project) {
            return new EmptyResponse(404);
        }

        $body   = $request->getParsedBody();
        $branch = is_array($body) && is_string($body['branch'] ?? null) ? $body['branch'] : null;
        if ($branch === null || $branch === '') {
            return new EmptyResponse(400);
        }

        if (in_array($branch, self::PROTECTED_BRANCHES, true)) {
            return new EmptyResponse(400);
        }

        $isActive = $this->environmentRepository->isActive($project->id, $branch);

        if ($isActive) {
            $this->eventDispatcher->dispatch(new BranchDeactivated($project->id, $branch));
        } else {
            $this->eventDispatcher->dispatch(new BranchActivated($project->id, $branch));
        }

        return $this->renderer->render($request, 'partial::project/branch-status', [
            'branch'      => $branch,
            'isActive'    => ! $isActive,
            'isProtected' => false,
            'projectId'   => $project->id->toString(),
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
            if (! $project instanceof Project) {
                return null;
            }

            if ($project->teamId->toString() !== $activeTeam->id->toString()) {
                return null;
            }

            return $project;
        } catch (Throwable) {
            return null;
        }
    }
}
