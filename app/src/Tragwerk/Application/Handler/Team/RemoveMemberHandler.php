<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Team;

use Mezzio\Authentication\UserInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Enum\TeamRole;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TeamMembership;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function array_filter;
use function array_merge;
use function array_unique;
use function array_values;
use function assert;
use function in_array;
use function is_array;
use function is_string;

final readonly class RemoveMemberHandler implements RequestHandlerInterface
{
    public function __construct(
        private TeamRepository $teamRepository,
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
        $actorIsOwner  = false;

        if ($team instanceof Team) {
            $teamId  = $team->id->toString();
            $ownerId = $team->ownerId->toString();
            $body    = $request->getParsedBody();

            $user = $request->getAttribute(UserInterface::class);
            assert($user instanceof UserInterface);
            $actorIsOwner = $this->teamRepository->roleOf(
                $team->id,
                UserIdentifier::fromString($user->getIdentity()),
            ) === TeamRole::Owner;

            $pendingRemovals = is_array($body) && is_array($body['usersToRemove'] ?? null)
                ? $body['usersToRemove']
                : [];

            $newRemoval = is_array($body) && is_string($body['userId'] ?? null) ? $body['userId'] : null;

            // The owner can never be removed, regardless of who triggers the action.
            $allToRemove = array_unique(array_merge(
                array_filter(
                    $pendingRemovals,
                    static fn (mixed $v) => is_string($v) && UserIdentifier::isValid($v) && $v !== $ownerId,
                ),
                $newRemoval !== null && UserIdentifier::isValid($newRemoval) && $newRemoval !== $ownerId
                    ? [$newRemoval]
                    : [],
            ));

            $memberships = array_values(array_filter(
                $this->teamRepository->getMembersWithRoles($team->id),
                static fn (TeamMembership $m) => ! in_array($m->user->id->toString(), $allToRemove, true),
            ));

            $usersToRemove = array_values($allToRemove);
        }

        // ManageMembers is enforced by TeamAuthorizationMiddleware before this handler.
        return $this->renderer->render($request, 'partial::team/member-list', [
            'memberships'   => $memberships,
            'teamId'        => $teamId,
            'canManage'     => true,
            'actorIsOwner'  => $actorIsOwner,
            'usersToRemove' => $usersToRemove,
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
