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
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Event\ProjectCreated;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;

final readonly class CreateHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
        private ServerRepository $serverRepository,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $activeTeam = $request->getAttribute('active_team');
        assert($activeTeam instanceof Team);

        $validationBag = null;

        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validationBag = $this->mapper->mapAndValidate($request, ProjectCreation::class);

            $user = $request->getAttribute(UserInterface::class);
            assert($user instanceof UserInterface);

            if (! $validationBag->hasErrors()) {
                $dto = $validationBag->getDto();
                assert($dto instanceof ProjectCreation);

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

        $servers = $this->serverRepository->getAll(teamId: $activeTeam->id);

        return $this->renderer->render($request, 'page::project/create', [
            'validationBag' => $validationBag,
            'servers'       => $servers,
        ]);
    }
}
