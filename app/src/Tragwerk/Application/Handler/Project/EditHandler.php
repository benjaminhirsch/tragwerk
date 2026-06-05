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
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Event\ProjectUpdated;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

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
        private RegistryRepository $registryRepository,
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
                'name'       => $project->name,
                'serverId'   => $project->serverId->toString(),
                'registryId' => $project->registryId->toString(),
            ]);
        }

        $servers             = iterator_to_array($this->serverRepository->getAll(teamId: $activeTeam->id), false);
        $serverProjectCounts = $this->projectRepository->countProjectsByServer($activeTeam->id);
        $registries          = iterator_to_array($this->registryRepository->getAll($activeTeam->id), false);

        return $this->renderer->render($request, 'page::project/edit', [
            'project'            => $project,
            'validationBag'      => $validationBag,
            'servers'            => $servers,
            'serverProjectCounts' => $serverProjectCounts,
            'registries'         => $registries,
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
