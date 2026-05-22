<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Team;

use Fig\Http\Message\RequestMethodInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Authentication\UserInterface;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Dto\Team\TeamUpdate;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Validation\ValidationBag;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Event\TeamUpdated;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
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
        private TeamRepository $teamRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $team = $this->resolveTeam($request);

        if (! $team instanceof Team) {
            return new RedirectResponse($this->urlHelper->generate('team'));
        }

        $validationBag = null;

        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validationBag = $this->mapper->mapAndValidate($request, TeamUpdate::class);

            if (! $validationBag->hasErrors()) {
                $update = $validationBag->getDto();
                assert($update instanceof TeamUpdate);

                $user = $request->getAttribute(UserInterface::class);
                assert($user instanceof UserInterface);

                $this->eventDispatcher->dispatch(new TeamUpdated(
                    $team->id,
                    $update,
                    UserIdentifier::fromString($user->getIdentity()),
                ));

                return new RedirectResponse($this->urlHelper->generate('team'));
            }
        }

        if ($validationBag === null) {
            $validationBag = new ValidationBag(['name' => $team->name]);
        }

        $allMembers    = iterator_to_array($this->teamRepository->getUsersByTeamId($team->id), false);
        $pendingRemove = $validationBag->getArrayValueByName('usersToRemove');
        $members       = $pendingRemove !== []
            ? array_values(array_filter(
                $allMembers,
                static fn (User $u) => ! in_array($u->id->toString(), $pendingRemove, true),
            ))
            : $allMembers;

        return $this->renderer->render($request, 'page::team/edit', [
            'team'          => $team,
            'validationBag' => $validationBag,
            'members'       => $members,
        ]);
    }

    private function resolveTeam(ServerRequestInterface $request): Team|null
    {
        $routeId = $request->getAttribute('id');
        if (! is_string($routeId) || ! TeamIdentifier::isValid($routeId)) {
            return null;
        }

        $raw = $request->getAttribute('user_teams');
        if (! is_array($raw)) {
            return null;
        }

        foreach ($raw as $team) {
            assert($team instanceof Team);
            if ($team->id->toString() === $routeId) {
                return $team;
            }
        }

        return null;
    }
}
