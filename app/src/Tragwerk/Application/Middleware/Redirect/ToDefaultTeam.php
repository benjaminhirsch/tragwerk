<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware\Redirect;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Authentication\UserInterface;
use Mezzio\Helper\UrlHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Helper\ListHelper;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;

final readonly class ToDefaultTeam implements MiddlewareInterface
{
    public function __construct(
        private UrlHelper $urlHelper,
        private TeamRepository $teamRepository,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute(UserInterface::class);
        assert($user instanceof UserInterface);

        $team = ListHelper::sort(
            $this->teamRepository->getByUserId(UserIdentifier::fromString($user->getIdentity())),
            'createdAt',
            'asc',
        )->current();

        if (! $team instanceof Team) {
            // A logged-in user without any team (e.g. seeded account, or default-team creation
            // failed) lands on team creation rather than a dead-end error page.
            return new RedirectResponse($this->urlHelper->generate('team.create'));
        }

        return new RedirectResponse($this->urlHelper->generate('team.show', [
            'id' => $team->id,
        ]));
    }
}
