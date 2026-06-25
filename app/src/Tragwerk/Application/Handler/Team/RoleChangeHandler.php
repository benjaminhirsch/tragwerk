<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Team;

use Mezzio\Authentication\UserInterface;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Enum\TeamRole;
use Tragwerk\Domain\Event\TeamMemberRoleChanged;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function array_filter;
use function array_values;
use function assert;
use function is_array;
use function is_string;

final readonly class RoleChangeHandler implements RequestHandlerInterface
{
    public function __construct(
        private TeamRepository $teamRepository,
        private EventDispatcherInterface $eventDispatcher,
        private ResponseRenderer $renderer,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $team = $this->resolveTeam($request);

        $memberships   = [];
        $usersToRemove = [];
        $teamId        = '';

        if ($team instanceof Team) {
            $teamId = $team->id->toString();
            $body   = $request->getParsedBody();

            $user = $request->getAttribute(UserInterface::class);
            assert($user instanceof UserInterface);
            $actorRole = $this->teamRepository->roleOf(
                $team->id,
                UserIdentifier::fromString($user->getIdentity()),
            );

            $this->applyChange($team, $body, $actorRole, $user->getIdentity());

            $usersToRemove = is_array($body) && is_array($body['usersToRemove'] ?? null)
                ? array_values(array_filter(
                    $body['usersToRemove'],
                    static fn (mixed $v) => is_string($v) && UserIdentifier::isValid($v),
                ))
                : [];

            $memberships = $this->teamRepository->getMembersWithRoles($team->id);
        }

        return $this->renderer->render($request, 'partial::team/member-list', [
            'memberships'   => $memberships,
            'teamId'        => $teamId,
            'canManage'     => true,
            'actorIsOwner'  => isset($actorRole) && $actorRole === TeamRole::Owner,
            'usersToRemove' => $usersToRemove,
        ]);
    }

    private function applyChange(
        Team $team,
        mixed $body,
        TeamRole|null $actorRole,
        string $actorIdentity,
    ): void {
        $targetId = is_array($body) && is_string($body['userId'] ?? null) ? $body['userId'] : null;
        $rawRole  = is_array($body) && is_string($body['role'] ?? null) ? $body['role'] : null;

        if ($targetId === null || ! UserIdentifier::isValid($targetId) || $rawRole === null) {
            return;
        }

        $newRole = TeamRole::tryFrom($rawRole);
        if ($newRole === null) {
            return;
        }

        $targetUserId = UserIdentifier::fromString($targetId);

        // Only actual members can be re-roled.
        if ($this->teamRepository->roleOf($team->id, $targetUserId) === null) {
            return;
        }

        // The current owner's role is immutable except through an explicit transfer
        // (which targets a *different* user and sets them to Owner).
        if ($targetId === $team->ownerId->toString()) {
            return;
        }

        // Granting Owner is an ownership transfer — only the current owner may do it.
        if ($newRole === TeamRole::Owner && $actorRole !== TeamRole::Owner) {
            return;
        }

        $this->eventDispatcher->dispatch(new TeamMemberRoleChanged(
            $team->id,
            $targetUserId,
            $newRole,
            UserIdentifier::fromString($actorIdentity),
        ));
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
