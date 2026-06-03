<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project;

use Fig\Http\Message\RequestMethodInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Authentication\UserInterface;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Dto\Project\ProjectUpdate;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Queue\Message\BuildEnvironment;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Validation\ValidationBag;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Enum\SwarmNodeRole;
use Tragwerk\Domain\Event\ProjectUpdated;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use Tragwerk\Infrastructure\Git\BareRepository;

use function _;
use function array_diff;
use function array_filter;
use function array_keys;
use function array_values;
use function assert;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function iterator_to_array;
use function trim;

final readonly class EditHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
        private ProjectRepository $projectRepository,
        private ServerRepository $serverRepository,
        private RegistryRepository $registryRepository,
        private DeployJobRepository $deployJobRepository,
        private BareRepository $bareRepository,
        private Producer $producer,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $this->resolveProject($request);

        if (! $project instanceof Project) {
            return new RedirectResponse($this->urlHelper->generate('project'));
        }

        $activeTeam = $request->getAttribute('active_team');
        assert($activeTeam instanceof Team);

        $validationBag = null;

        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validationBag = $this->mapper->mapAndValidate($request, ProjectUpdate::class);

            if (! $validationBag->hasErrors()) {
                $dto = $validationBag->getDto();
                assert($dto instanceof ProjectUpdate);

                $serverId = ServerIdentifier::fromString($dto->serverId);
                if ($this->projectRepository->isServerInUse($serverId, excludeProjectId: $project->id)) {
                    $message       = _('This server is already assigned to another project');
                    $validationBag = $validationBag->withError('serverId', $message);
                }
            }

            if (! $validationBag->hasErrors()) {
                $dto = $validationBag->getDto();
                assert($dto instanceof ProjectUpdate);

                if ($dto->swarmEnabled && ($dto->registryId === null || trim($dto->registryId) === '')) {
                    $validationBag = $validationBag->withError(
                        'registryId',
                        _('Docker Swarm requires a container registry'),
                    );
                }
            }

            if (! $validationBag->hasErrors()) {
                $dto = $validationBag->getDto();
                assert($dto instanceof ProjectUpdate);

                $swarmNodes = [];
                if ($dto->swarmEnabled) {
                    [$validationBag, $swarmNodes] = $this->validateSwarmNodes(
                        $request,
                        $activeTeam->id,
                        $project->id,
                        $validationBag,
                        $dto->serverId,
                    );
                }

                if (! $validationBag->hasErrors()) {
                    $user = $request->getAttribute(UserInterface::class);
                    assert($user instanceof UserInterface);

                    $swarmJustEnabled  = ! $project->swarmEnabled && $dto->swarmEnabled;
                    $swarmJustDisabled = $project->swarmEnabled && ! $dto->swarmEnabled;

                    $this->eventDispatcher->dispatch(new ProjectUpdated(
                        $project->id,
                        $dto,
                        UserIdentifier::fromString($user->getIdentity()),
                        $swarmNodes,
                    ));

                    if ($swarmJustEnabled || $swarmJustDisabled) {
                        $this->triggerSwarmRedeploy($project->id);
                    }

                    return new RedirectResponse(
                        $this->urlHelper->generate('project.show', ['id' => $project->id->toString()]),
                    );
                }
            }
        }

        if ($validationBag === null) {
            $validationBag = new ValidationBag([
                'name'         => $project->name,
                'serverId'     => $project->serverId->toString(),
                'registryId'   => $project->registryId?->toString() ?? '',
                'swarmEnabled' => $project->swarmEnabled ? '1' : '',
            ]);
        }

        $usedServerIds = $this->getUsedServerIds($activeTeam->id, excludeProjectId: $project->id);
        /** @var list<Server> $allServers */
        $allServers = iterator_to_array($this->serverRepository->getAll(teamId: $activeTeam->id), false);
        $servers    = array_filter(
            $allServers,
            static fn (Server $s): bool => ! isset($usedServerIds[$s->id->toString()]),
        );

        $registries    = iterator_to_array($this->registryRepository->getAll($activeTeam->id), false);
        $existingNodes = $this->projectRepository->getSwarmNodes($project->id);

        return $this->renderer->render($request, 'page::project/edit', [
            'project'       => $project,
            'validationBag' => $validationBag,
            'servers'       => $servers,
            'allServers'    => $allServers,
            'registries'    => $registries,
            'existingNodes' => $existingNodes,
        ]);
    }

    private function triggerSwarmRedeploy(ProjectIdentifier $projectId): void
    {
        $branches = $this->deployJobRepository->getDeployedBranches($projectId);

        foreach ($branches as $branch) {
            try {
                $commits = $this->bareRepository->getCommits($projectId->toString(), $branch, 1);
                if ($commits === []) {
                    continue;
                }

                $this->producer->sendMessage(new BuildEnvironment(
                    projectId: $projectId->toString(),
                    branch:    $branch,
                    commitSha: $commits[0]->hash,
                ));
            } catch (Throwable) {
                // skip branch if commits unavailable
            }
        }
    }

    /** @return array{0: ValidationBag, 1: list<array{serverId: string, role: string, isStorage: bool}>} */
    private function validateSwarmNodes(
        ServerRequestInterface $request,
        TeamIdentifier $teamId,
        ProjectIdentifier $currentProjectId,
        ValidationBag $validationBag,
        string $primaryServerId = '',
    ): array {
        $body          = $request->getParsedBody();
        $rawNodes      = is_array($body) && is_array($body['swarmNodes'] ?? null) ? $body['swarmNodes'] : [];
        $selectedIds   = array_values(array_diff(array_keys($rawNodes), [$primaryServerId]));
        $roles         = is_array($body) && is_array($body['swarmNodeRoles'] ?? null) ? $body['swarmNodeRoles'] : [];
        $storageNodeId = is_array($body) && is_string($body['swarmStorageNodeId'] ?? null)
            ? $body['swarmStorageNodeId']
            : null;

        if (count($selectedIds) < 2) {
            $msg = _('Swarm mode requires at least 2 additional nodes (3 servers total)');

            return [$validationBag->withError('swarmNodes', $msg), []];
        }

        $managerCount  = 1;
        $swarmNodes    = [];
        $teamServerIds = $this->getTeamServerIds($teamId);

        foreach ($selectedIds as $serverId) {
            if (! is_string($serverId) || ! ServerIdentifier::isValid($serverId)) {
                return [$validationBag->withError('swarmNodes', _('Invalid server selection')), []];
            }

            if (! isset($teamServerIds[$serverId])) {
                $msg = _('Selected server does not belong to your team');

                return [$validationBag->withError('swarmNodes', $msg), []];
            }

            if (
                $this->projectRepository->isServerInSwarmCluster(
                    ServerIdentifier::fromString($serverId),
                    excludeProjectId: $currentProjectId,
                )
            ) {
                $msg = _('One of the selected servers is already in use');

                return [$validationBag->withError('swarmNodes', $msg), []];
            }

            $role = is_string($roles[$serverId] ?? null) ? $roles[$serverId] : SwarmNodeRole::Worker->value;
            if (! in_array($role, [SwarmNodeRole::Manager->value, SwarmNodeRole::Worker->value], true)) {
                $role = SwarmNodeRole::Worker->value;
            }

            if ($role === SwarmNodeRole::Manager->value) {
                $managerCount++;
            }

            $swarmNodes[] = [
                'serverId'  => $serverId,
                'role'      => $role,
                'isStorage' => $serverId === $storageNodeId,
            ];
        }

        if ($managerCount % 2 === 0) {
            $msg = _('Total manager count must be odd (1, 3, 5, …) for Raft quorum');

            return [$validationBag->withError('swarmNodes', $msg), []];
        }

        $storageNodes = array_filter($swarmNodes, static fn (array $n): bool => $n['isStorage']);
        if (count($storageNodes) !== 1) {
            $msg = _('Exactly one storage node must be selected');

            return [$validationBag->withError('swarmStorageNodeId', $msg), []];
        }

        return [$validationBag, $swarmNodes];
    }

    /** @return array<string, true> */
    private function getUsedServerIds(TeamIdentifier $teamId, ProjectIdentifier|null $excludeProjectId = null): array
    {
        $used = [];

        foreach ($this->projectRepository->getAll(teamId: $teamId) as $project) {
            assert($project instanceof Project);
            if ($excludeProjectId !== null && $project->id->toString() === $excludeProjectId->toString()) {
                continue;
            }

            $used[$project->serverId->toString()] = true;
        }

        return $used;
    }

    /** @return array<string, true> */
    private function getTeamServerIds(TeamIdentifier $teamId): array
    {
        $ids = [];

        foreach ($this->serverRepository->getAll(teamId: $teamId) as $server) {
            assert($server instanceof Server);
            $ids[$server->id->toString()] = true;
        }

        return $ids;
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
