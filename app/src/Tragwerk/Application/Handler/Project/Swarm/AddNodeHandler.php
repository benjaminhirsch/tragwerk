<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project\Swarm;

use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\SwarmNode;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Event\SwarmNodeAdded;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;

use function _;
use function array_filter;
use function array_values;
use function assert;
use function is_array;
use function is_string;
use function iterator_to_array;

final readonly class AddNodeHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectRepository $projectRepository,
        private ServerRepository $serverRepository,
        private DeployJobRepository $deployJobRepository,
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

        if (! $project->swarmEnabled) {
            return new EmptyResponse(400);
        }

        $body     = $request->getParsedBody();
        $serverId = is_array($body) && is_string($body['serverId'] ?? null) ? $body['serverId'] : '';

        $error = $this->validate($serverId, $project);

        if ($error === null) {
            $this->eventDispatcher->dispatch(new SwarmNodeAdded(
                $project->id,
                ServerIdentifier::fromString($serverId),
            ));
        }

        $activeTeam = $request->getAttribute('active_team');
        assert($activeTeam instanceof Team);

        return $this->renderSwarmSection($request, $project, $activeTeam, $error);
    }

    private function validate(string $serverId, Project $project): string|null
    {
        if ($serverId === '' || ! ServerIdentifier::isValid($serverId)) {
            return _('Please select a server');
        }

        $serverIdentifier = ServerIdentifier::fromString($serverId);

        if ($serverIdentifier->toString() === $project->serverId->toString()) {
            return _('Cannot add the primary manager as a swarm node');
        }

        if ($this->projectRepository->swarmNodeExists($project->id, $serverIdentifier)) {
            return _('This server is already a member of this cluster');
        }

        if ($this->projectRepository->isServerInSwarmCluster($serverIdentifier, excludeProjectId: $project->id)) {
            return _('This server is already in use by another project');
        }

        return null;
    }

    private function renderSwarmSection(
        ServerRequestInterface $request,
        Project $project,
        Team $team,
        string|null $error,
    ): ResponseInterface {
        $nodes     = $this->projectRepository->getSwarmNodes($project->id);
        $deployed  = $this->deployJobRepository->hasAnyCompletedDeploy($project->id);
        $available = $this->getAvailableServers($team->id, $project);

        return $this->renderer->render($request, 'partial::project/swarm-nodes', [
            'project'     => $project,
            'nodes'       => $nodes,
            'deployed'    => $deployed,
            'available'   => $available,
            'serverNames' => $this->buildServerNames($project, $nodes),
            'error'       => $error,
        ]);
    }

    /**
     * @param list<SwarmNode> $nodes
     *
     * @return array<string, string>
     */
    private function buildServerNames(Project $project, array $nodes): array
    {
        $names = [];
        $ids   = [$project->serverId];
        foreach ($nodes as $node) {
            $ids[] = $node->serverId;
        }

        foreach ($ids as $serverId) {
            try {
                $s = $this->serverRepository->getById($serverId);
                if ($s instanceof Server) {
                    $names[$serverId->toString()] = $s->name;
                }
            } catch (Throwable) {
            }
        }

        return $names;
    }

    /** @return list<Server> */
    private function getAvailableServers(
        TeamIdentifier $teamId,
        Project $project,
    ): array {
        /** @var list<Server> $all */
        $all = iterator_to_array($this->serverRepository->getAll(teamId: $teamId), false);

        return array_values(array_filter(
            $all,
            function (Server $server) use ($project): bool {
                if ($server->id->toString() === $project->serverId->toString()) {
                    return false;
                }

                if ($this->projectRepository->swarmNodeExists($project->id, $server->id)) {
                    return false;
                }

                return ! $this->projectRepository->isServerInSwarmCluster($server->id, $project->id);
            },
        ));
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
