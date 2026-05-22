<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Team;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function array_filter;
use function array_merge;
use function array_unique;
use function array_values;
use function assert;
use function in_array;
use function is_array;
use function is_string;
use function iterator_to_array;

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

        $members       = [];
        $usersToRemove = [];
        $ownerId       = '';
        $teamId        = '';

        if ($team instanceof Team) {
            $teamId  = $team->id->toString();
            $ownerId = $team->ownerId->toString();
            $body    = $request->getParsedBody();

            $pendingRemovals = is_array($body) && is_array($body['usersToRemove'] ?? null)
                ? $body['usersToRemove']
                : [];

            $newRemoval = is_array($body) && is_string($body['userId'] ?? null) ? $body['userId'] : null;

            $allToRemove = array_unique(array_merge(
                array_filter($pendingRemovals, static fn (mixed $v) => is_string($v) && UserIdentifier::isValid($v)),
                $newRemoval !== null && UserIdentifier::isValid($newRemoval) && $newRemoval !== $ownerId
                    ? [$newRemoval]
                    : [],
            ));

            $allMembers = iterator_to_array($this->teamRepository->getUsersByTeamId($team->id), false);

            $members = array_values(array_filter(
                $allMembers,
                static fn (User $u) => ! in_array($u->id->toString(), $allToRemove, true),
            ));

            $usersToRemove = array_values($allToRemove);
        }

        return $this->renderer->render($request, 'partial::team/member-list', [
            'members'       => $members,
            'teamId'        => $teamId,
            'ownerId'       => $ownerId,
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
