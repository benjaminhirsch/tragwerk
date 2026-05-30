<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project;

use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

use function assert;
use function is_string;

final readonly class DeployLogHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectRepository $projectRepository,
        private DeployJobRepository $deployJobRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $this->resolveProject($request);
        if (! $project instanceof Project) {
            return new EmptyResponse(404);
        }

        $params = $request->getQueryParams();
        $branch = is_string($params['branch'] ?? null) ? $params['branch'] : null;
        if ($branch === null || $branch === '') {
            return new EmptyResponse(400);
        }

        $job        = $this->deployJobRepository->getLatestByProjectAndBranch($project->id, $branch);
        $activeJobs = $this->deployJobRepository->getActiveByProjectAndBranch($project->id, $branch);

        return $this->renderer->render($request, 'page::project/_deploy_log', [
            'project'      => $project,
            'branch'       => $branch,
            'job'          => $job,
            'activeJobs'   => $activeJobs,
            'forcePolling' => false,
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

            if ($project->teamId->toString() !== $activeTeam->id->toString()) {
                return null;
            }

            return $project;
        } catch (Throwable) {
            return null;
        }
    }
}
