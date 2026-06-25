<?php

declare(strict_types=1);

namespace Tragwerk\Application\Security;

use Mezzio\Authentication\UserInterface;
use Mezzio\Authorization\AuthorizationInterface;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Tragwerk\Domain\Enum\TeamPermission;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function is_string;

/**
 * Resource-scoped authorization for teams.
 *
 * The mezzio AuthorizationInterface passes a "$role" string; here it carries the
 * required TeamPermission value (see {@see TeamPermission}). The actual role is
 * resolved per request from the team identified by the route `id` attribute and
 * the authenticated user — team membership is not a global user role.
 */
final readonly class TeamAuthorization implements AuthorizationInterface
{
    public function __construct(
        private TeamRepository $teamRepository,
    ) {
    }

    #[Override]
    public function isGranted(string $role, ServerRequestInterface $request): bool
    {
        $permission = TeamPermission::tryFrom($role);
        if ($permission === null) {
            return false;
        }

        $user = $request->getAttribute(UserInterface::class);
        if (! $user instanceof UserInterface) {
            return false;
        }

        $routeId = $request->getAttribute('id');
        if (! is_string($routeId) || ! TeamIdentifier::isValid($routeId)) {
            return false;
        }

        $teamRole = $this->teamRepository->roleOf(
            TeamIdentifier::fromString($routeId),
            UserIdentifier::fromString($user->getIdentity()),
        );

        return $teamRole?->can($permission) ?? false;
    }
}
