<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Team;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\ValueObject\TeamIdentifier;

use function assert;
use function is_array;
use function is_string;
use function iterator_to_array;

final readonly class ShowHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private TeamRepository $teamRepository,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $team = $this->resolveTeam($request);

        if (! $team instanceof Team) {
            return new RedirectResponse($this->urlHelper->generate('team'));
        }

        $members = iterator_to_array($this->teamRepository->getUsersByTeamId($team->id), false);

        return $this->renderer->render($request, 'page::team/show', [
            'team'    => $team,
            'members' => $members,
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
