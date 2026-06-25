<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Team;

use Mezzio\Authentication\UserInterface;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Dto\Team\InviteMembers;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Validation\ValidationBag;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Enum\TeamPermission;
use Tragwerk\Domain\Enum\TeamRole;
use Tragwerk\Domain\Event\TeamMembersInvited;
use Tragwerk\Domain\Repository\TeamInvitationRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;
use function is_array;
use function is_string;

final readonly class InviteMembersHandler implements RequestHandlerInterface
{
    private const int RECENT_INVITATIONS_LIMIT = 50;

    public function __construct(
        private TeamRepository $teamRepository,
        private TeamInvitationRepository $teamInvitationRepository,
        private GenericMapper $mapper,
        private EventDispatcherInterface $eventDispatcher,
        private ResponseRenderer $renderer,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $team = $this->resolveTeam($request);
        if (! $team instanceof Team) {
            return $this->renderer->render($request, 'partial::team/members-manage', ['teamId' => '']);
        }

        $user = $request->getAttribute(UserInterface::class);
        assert($user instanceof UserInterface);
        $actorRole = $this->teamRepository->roleOf(
            $team->id,
            UserIdentifier::fromString($user->getIdentity()),
        );

        $validationBag = $this->mapper->mapAndValidate($request, InviteMembers::class);

        if (! $validationBag->hasErrors()) {
            $invite = $validationBag->getDto();
            assert($invite instanceof InviteMembers);

            $this->eventDispatcher->dispatch(new TeamMembersInvited(
                $team->id,
                $invite->emailsToInvite,
                $invite->rolesToInvite,
                UserIdentifier::fromString($user->getIdentity()),
            ));

            // Successful invite: reset the entry fields.
            $validationBag = null;
        }

        return $this->renderer->render(
            $request,
            'partial::team/members-manage',
            $this->view($team, $actorRole, $validationBag),
        );
    }

    /** @return array<non-empty-string, mixed> */
    private function view(Team $team, TeamRole|null $actorRole, ValidationBag|null $validation): array
    {
        return [
            'teamId'             => $team->id->toString(),
            'memberships'        => $this->teamRepository->getMembersWithRoles($team->id),
            'pendingInvitations' => $this->teamInvitationRepository->getRecentByTeam(
                $team->id,
                self::RECENT_INVITATIONS_LIMIT,
            ),
            'canManage'          => $actorRole?->can(TeamPermission::ManageMembers) ?? false,
            'actorIsOwner'       => $actorRole === TeamRole::Owner,
            'emails'             => $validation?->getArrayValueByName('emailsToInvite') ?: [''],
            'roles'              => $validation?->getArrayValueByName('rolesToInvite') ?: [],
            'validation'         => $validation,
        ];
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
