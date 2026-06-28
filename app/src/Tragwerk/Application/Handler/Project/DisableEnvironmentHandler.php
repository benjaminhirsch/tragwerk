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
use Tragwerk\Application\Queue\Message\StopEnvironmentDocker;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\EnvironmentStateRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Infrastructure\Git\BareRepository;

use function in_array;
use function is_array;
use function is_string;

final readonly class DisableEnvironmentHandler implements RequestHandlerInterface
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private ServerRepository $serverRepository,
        private BareRepository $bareRepository,
        private EnvironmentStateRepository $environmentStateRepository,
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
            $branches = $this->bareRepository->getBranches($project->id->toString());
        } catch (Throwable) {
            $branches = [];
        }

        if (! in_array($branch, $branches, true)) {
            return new EmptyResponse(404);
        }

        $this->environmentStateRepository->disable($project->id, $branch);

        try {
            $server = $this->serverRepository->getById($project->serverId);
            if ($server instanceof Server && $server->credentialId !== null) {
                $this->producer->sendMessage(new StopEnvironmentDocker(
                    projectId:    $project->id->toString(),
                    branch:       $branch,
                    host:         $server->host,
                    port:         $server->port,
                    credentialId: $server->credentialId->toString(),
                ));
            }
        } catch (Throwable) {
            // do not fail the request if the server is unavailable
        }

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
