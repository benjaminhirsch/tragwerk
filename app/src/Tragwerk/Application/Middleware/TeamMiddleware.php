<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware;

use Mezzio\Authentication\UserInterface;
use Mezzio\Router\RouteResult;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function array_key_exists;
use function array_key_first;
use function assert;
use function is_string;
use function iterator_to_array;

final readonly class TeamMiddleware implements MiddlewareInterface
{
    public const string SESSION_KEY = 'active_team_id';

    public function __construct(
        private TeamRepository $teamRepository,
        private UserRepository $userRepository,
    ) {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute(UserInterface::class);
        if (! $user instanceof UserInterface) {
            return $handler->handle($request);
        }

        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        assert($session instanceof SessionInterface);

        $route = $request->getAttribute(RouteResult::class);
        assert($route instanceof RouteResult);

        $userId = UserIdentifier::fromString($user->getIdentity());
        $teams  = iterator_to_array($this->teamRepository->getByUserId($userId), false);

        if ($teams === []) {
            return $handler->handle(
                $request
                    ->withAttribute('user_teams', [])
                    ->withAttribute('active_team', null),
            );
        }

        $teamMap = [];
        foreach ($teams as $team) {
            assert($team instanceof Team);
            $teamMap[$team->id->toString()] = $team;
        }

        if ($route->getMatchedRouteName() === 'team.show') {
            $session->set(self::SESSION_KEY, $request->getAttribute('id'));
        }

        $sessionTeamId = $session->get(self::SESSION_KEY);
        if (is_string($sessionTeamId) && array_key_exists($sessionTeamId, $teamMap)) {
            $activeTeam = $teamMap[$sessionTeamId];
        } else {
            $activeTeam = $this->resolveFromLastActive($userId, $teamMap);
            $session->set(self::SESSION_KEY, $activeTeam->id->toString());
        }

        return $handler->handle(
            $request
                ->withAttribute('user_teams', $teams)
                ->withAttribute('active_team', $activeTeam),
        );
    }

    /** @param array<string, Team> $teamMap */
    private function resolveFromLastActive(UserIdentifier $userId, array $teamMap): Team
    {
        $lastActiveId = $this->userRepository->getLastActiveTeamId($userId);

        if ($lastActiveId instanceof TeamIdentifier) {
            $idString = $lastActiveId->toString();
            if (array_key_exists($idString, $teamMap)) {
                return $teamMap[$idString];
            }
        }

        $firstKey = array_key_first($teamMap);
        assert(is_string($firstKey));

        return $teamMap[$firstKey];
    }
}
