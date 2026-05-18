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
use Tragwerk\Application\Dto\Project\ProjectUpdate;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Validation\ValidationBag;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Event\ProjectUpdated;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function array_filter;
use function array_values;
use function assert;
use function in_array;
use function is_array;
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
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $this->resolveProject($request);

        if (! $project instanceof Project) {
            return new RedirectResponse($this->urlHelper->generate('project'));
        }

        $validationBag = null;

        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validationBag = $this->mapper->mapAndValidate($request, ProjectUpdate::class);

            if (! $validationBag->hasErrors()) {
                $update = $validationBag->getDto();
                assert($update instanceof ProjectUpdate);

                $user = $request->getAttribute(UserInterface::class);
                assert($user instanceof UserInterface);

                $this->eventDispatcher->dispatch(new ProjectUpdated(
                    $project->id,
                    $update,
                    UserIdentifier::fromString($user->getIdentity()),
                ));

                return new RedirectResponse($this->urlHelper->generate('project'));
            }
        }

        if ($validationBag === null) {
            $validationBag = new ValidationBag(['name' => $project->name], null, []);
        }

        $allMembers    = iterator_to_array($this->projectRepository->getUsersByProjectId($project->id), false);
        $pendingRemove = $validationBag->getArrayValueByName('usersToRemove');
        $members       = $pendingRemove !== []
            ? array_values(array_filter(
                $allMembers,
                static fn (User $u) => ! in_array($u->id->toString(), $pendingRemove, true),
            ))
            : $allMembers;

        return $this->renderer->render($request, 'page::project/edit', [
            'project'       => $project,
            'validationBag' => $validationBag,
            'members'       => $members,
        ]);
    }

    private function resolveProject(ServerRequestInterface $request): Project|null
    {
        $routeId = $request->getAttribute('id');
        if (! is_string($routeId) || ! ProjectIdentifier::isValid($routeId)) {
            return null;
        }

        $raw = $request->getAttribute('user_projects');
        if (! is_array($raw)) {
            return null;
        }

        foreach ($raw as $project) {
            assert($project instanceof Project);
            if ($project->id->toString() === $routeId) {
                return $project;
            }
        }

        return null;
    }
}
