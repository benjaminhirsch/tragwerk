<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Queue\Message\BuildEnvironment;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Infrastructure\Git\BareRepository;

use function is_array;
use function is_string;

final readonly class RedeployEnvironmentHandler implements RequestHandlerInterface
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private BareRepository $bareRepository,
        private Producer $producer,
        private UrlHelper $urlHelper,
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

        try {
            $commits = $this->bareRepository->getCommits($project->id->toString(), $branch, 1);
        } catch (Throwable) {
            $commits = [];
        }

        if ($commits === []) {
            return new EmptyResponse(400);
        }

        $this->producer->sendMessage(new BuildEnvironment(
            projectId:    $project->id->toString(),
            branch:       $branch,
            commitSha:    $commits[0]->hash,
            forceRebuild: true,
        ));

        return new RedirectResponse($this->urlHelper->generate('environment.show', [], ['id' => $branch]));
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

            return $project->teamId->toString() === $activeTeam->id->toString() ? $project : null;
        } catch (Throwable) {
            return null;
        }
    }
}
