<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Team;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Event\TeamDeleted;
use Tragwerk\Domain\ValueObject\TeamIdentifier;

use function assert;
use function count;
use function is_array;
use function is_string;

final readonly class DeleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $raw  = $request->getAttribute('user_teams');
        $team = $this->resolveTeam($request, $raw);

        // Owner-only authorization is enforced by TeamAuthorizationMiddleware; here we
        // additionally guard the product rule that a user must keep at least one team.
        if ($team instanceof Team && is_array($raw) && count($raw) > 1) {
            $this->eventDispatcher->dispatch(new TeamDeleted($team->id));
        }

        return new RedirectResponse($this->urlHelper->generate('team'));
    }

    private function resolveTeam(ServerRequestInterface $request, mixed $raw): Team|null
    {
        $routeId = $request->getAttribute('id');
        if (! is_string($routeId) || ! TeamIdentifier::isValid($routeId)) {
            return null;
        }

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
