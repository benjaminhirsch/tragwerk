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
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

use function assert;
use function is_string;

final readonly class TabHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectRepository $projectRepository,
        private ServerRepository $serverRepository,
        private TeamRepository $teamRepository,
        private string $gitSshHost,
        private string $gitSshRepoBase,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $this->resolveProject($request);

        if (! $project instanceof Project) {
            return new EmptyResponse(404);
        }

        $tab = $request->getAttribute('tab');

        return match ($tab) {
            'overview'     => $this->renderOverview($request, $project),
            'environments' => $this->renderEnvironments($request, $project),
            default        => new EmptyResponse(404),
        };
    }

    private function renderOverview(ServerRequestInterface $request, Project $project): ResponseInterface
    {
        $server = $this->serverRepository->getById($project->serverId);
        $team   = $this->teamRepository->getById($project->teamId);

        assert($server instanceof Server);
        assert($team instanceof Team);

        return $this->renderer->render($request, 'page::project/tab/overview', [
            'project' => $project,
            'server'  => $server,
            'team'    => $team,
        ]);
    }

    private function renderEnvironments(ServerRequestInterface $request, Project $project): ResponseInterface
    {
        $cloneUrl = 'git@' . $this->gitSshHost . ':' . $this->gitSshRepoBase . '/' . $project->id->toString();

        return $this->renderer->render($request, 'page::project/tab/environments', [
            'project'  => $project,
            'cloneUrl' => $cloneUrl,
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
