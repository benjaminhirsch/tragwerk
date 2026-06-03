<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project;

use Fig\Http\Message\RequestMethodInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Authentication\UserInterface;
use Mezzio\Helper\UrlHelper;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Dto\Project\ProjectCreation;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Validation\ValidationBag;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Enum\SwarmNodeRole;
use Tragwerk\Domain\Event\ProjectCreated;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

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

final readonly class CreateHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
        private ProjectRepository $projectRepository,
        private ServerRepository $serverRepository,
        private RegistryRepository $registryRepository,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $activeTeam = $request->getAttribute('active_team');
        assert($activeTeam instanceof Team);

        $validationBag = null;

        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validationBag = $this->mapper->mapAndValidate($request, ProjectCreation::class);

            if (! $validationBag->hasErrors()) {
                $dto = $validationBag->getDto();
                assert($dto instanceof ProjectCreation);

                $serverId = ServerIdentifier::fromString($dto->serverId);
                if ($this->projectRepository->isServerInUse($serverId)) {
                    $message       = _('This server is already assigned to another project');
                    $validationBag = $validationBag->withError('serverId', $message);
                }
            }

            if (! $validationBag->hasErrors()) {
                $dto = $validationBag->getDto();
                assert($dto instanceof ProjectCreation);

                if ($dto->swarmEnabled && ($dto->registryId === null || trim($dto->registryId) === '')) {
                    $validationBag = $validationBag->withError(
                        'registryId',
                        _('Docker Swarm requires a container registry'),
                    );
                }
            }

            if (! $validationBag->hasErrors()) {
                $dto = $validationBag->getDto();
                assert($dto instanceof ProjectCreation);

                $swarmNodes = [];
                if ($dto->swarmEnabled) {
                    [$validationBag, $swarmNodes] = $this->validateSwarmNodes(
                        $request,
                        $activeTeam->id,
                        $validationBag,
                        $dto->serverId,
                    );
                }

                if (! $validationBag->hasErrors()) {
                    $user = $request->getAttribute(UserInterface::class);
                    assert($user instanceof UserInterface);

                    $projectId = ProjectIdentifier::create();

                    $this->eventDispatcher->dispatch(new ProjectCreated(
                        $dto,
                        $projectId,
                        $activeTeam->id,
                        UserIdentifier::fromString($user->getIdentity()),
                        $swarmNodes,
                    ));

                    return new RedirectResponse(
                        $this->urlHelper->generate('project.show', ['id' => $projectId->toString()]),
                    );
                }
            }
        }

        $usedServerIds = $this->getUsedServerIds($activeTeam->id);
        /** @var list<Server> $allServers */
        $allServers = iterator_to_array($this->serverRepository->getAll(teamId: $activeTeam->id), false);
        $servers    = array_filter(
            $allServers,
            static fn (Server $s): bool => ! isset($usedServerIds[$s->id->toString()]),
        );

        $registries = iterator_to_array($this->registryRepository->getAll($activeTeam->id), false);

        return $this->renderer->render($request, 'page::project/create', [
            'validationBag' => $validationBag,
            'servers'       => $servers,
            'allServers'    => $allServers,
            'registries'    => $registries,
        ]);
    }

    /** @return array{0: ValidationBag, 1: list<array{serverId: string, role: string, isStorage: bool}>} */
    private function validateSwarmNodes(
        ServerRequestInterface $request,
        TeamIdentifier $teamId,
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

        $managerCount  = 1; // primary server is always manager
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

            if ($this->projectRepository->isServerInSwarmCluster(ServerIdentifier::fromString($serverId))) {
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
    private function getUsedServerIds(TeamIdentifier $teamId): array
    {
        $used = [];

        foreach ($this->projectRepository->getAll(teamId: $teamId) as $project) {
            assert($project instanceof Project);
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
}
