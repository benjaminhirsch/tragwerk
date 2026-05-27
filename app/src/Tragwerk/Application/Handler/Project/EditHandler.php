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
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Validation\ValidationBag;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Event\ProjectUpdated;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function _;
use function array_filter;
use function assert;
use function is_string;
use function iterator_to_array;

final readonly class EditHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
        private ProjectRepository $projectRepository,
        private ServerRepository $serverRepository,
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

                $user = $request->getAttribute(UserInterface::class);
                assert($user instanceof UserInterface);

                $this->eventDispatcher->dispatch(new ProjectUpdated(
                    $project->id,
                    $dto,
                    UserIdentifier::fromString($user->getIdentity()),
                ));

                return new RedirectResponse(
                    $this->urlHelper->generate('project.show', ['id' => $project->id->toString()]),
                );
            }
        }

        if ($validationBag === null) {
            $validationBag = new ValidationBag([
                'name'     => $project->name,
                'serverId' => $project->serverId->toString(),
            ]);
        }

        $usedServerIds = $this->getUsedServerIds($activeTeam->id, excludeProjectId: $project->id);
        /** @var list<Server> $allServers */
        $allServers = iterator_to_array($this->serverRepository->getAll(teamId: $activeTeam->id), false);
        $servers    = array_filter(
            $allServers,
            static fn (Server $s): bool => ! isset($usedServerIds[$s->id->toString()]),
        );

        return $this->renderer->render($request, 'page::project/edit', [
            'project'       => $project,
            'validationBag' => $validationBag,
            'servers'       => $servers,
        ]);
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
