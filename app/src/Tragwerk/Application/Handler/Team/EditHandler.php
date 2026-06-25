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
use Tragwerk\Domain\Enum\TeamPermission;
use Tragwerk\Domain\Enum\TeamRole;
use Tragwerk\Domain\Event\TeamUpdated;
use Tragwerk\Domain\Repository\TeamInvitationRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TeamMembership;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function array_filter;
use function array_values;
use function assert;
use function count;
use function in_array;
use function is_array;
use function is_string;

final readonly class EditHandler implements RequestHandlerInterface
{
    private const int RECENT_INVITATIONS_LIMIT = 50;

    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
        private TeamRepository $teamRepository,
        private TeamInvitationRepository $teamInvitationRepository,
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

                return new RedirectResponse(
                    $this->urlHelper->generate('team.show', ['id' => $team->id->toString()]),
                );
            }
        }

        if ($validationBag === null) {
            $validationBag = new ValidationBag(['name' => $team->name]);
        }

        $user = $request->getAttribute(UserInterface::class);
        assert($user instanceof UserInterface);
        $actorRole = $this->teamRepository->roleOf(
            $team->id,
            UserIdentifier::fromString($user->getIdentity()),
        );

        $allMembers    = $this->teamRepository->getMembersWithRoles($team->id);
        $pendingRemove = $validationBag->getArrayValueByName('usersToRemove');
        $memberships   = $pendingRemove !== []
            ? array_values(array_filter(
                $allMembers,
                static fn (TeamMembership $m) => ! in_array($m->user->id->toString(), $pendingRemove, true),
            ))
            : $allMembers;

        $raw = $request->getAttribute('user_teams');

        return $this->renderer->render($request, 'page::team/edit', [
            'team'          => $team,
            'validationBag' => $validationBag,
            'memberships'   => $memberships,
            'pendingInvitations' => $this->teamInvitationRepository->getRecentByTeam(
                $team->id,
                self::RECENT_INVITATIONS_LIMIT,
            ),
            'canManage'     => $actorRole?->can(TeamPermission::ManageMembers) ?? false,
            'actorIsOwner'  => $actorRole === TeamRole::Owner,
            // Deleting requires the DeleteTeam permission and that the user keeps at least one team.
            'canDelete'     => ($actorRole?->can(TeamPermission::DeleteTeam) ?? false)
                && is_array($raw) && count($raw) > 1,
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
