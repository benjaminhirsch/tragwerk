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
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Event\ProjectCreated;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function _;
use function array_filter;
use function assert;
use function iterator_to_array;

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
                    $validationBag = $validationBag->withError(
                        'serverId',
                        _('This server is already assigned to another project'),
                    );
                }
            }

            if (! $validationBag->hasErrors()) {
                $dto = $validationBag->getDto();
                assert($dto instanceof ProjectCreation);

                $user = $request->getAttribute(UserInterface::class);
                assert($user instanceof UserInterface);

                $projectId = ProjectIdentifier::create();

                $this->eventDispatcher->dispatch(new ProjectCreated(
                    $dto,
                    $projectId,
                    $activeTeam->id,
                    UserIdentifier::fromString($user->getIdentity()),
                ));

                return new RedirectResponse(
                    $this->urlHelper->generate('project.show', ['id' => $projectId->toString()]),
                );
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
            'registries'    => $registries,
        ]);
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
}
