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
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;

final readonly class ToDefaultTeam implements MiddlewareInterface
{
    public function __construct(
        private UrlHelper $urlHelper,
        private TeamRepository $teamRepository,
        private ResponseRenderer $responseRenderer,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute(UserInterface::class);
        assert($user instanceof UserInterface);

        $team = ListHelper::sortGenerator(
            $this->teamRepository->getByUserId(UserIdentifier::fromString($user->getIdentity())),
            'created_at',
            'asc',
        )->current();

        if (! $team instanceof Team) {
            return $this->responseRenderer->render($request, 'page::error/404', [], 404);
        }

        return new RedirectResponse($this->urlHelper->generate('team.show', [
            'id' => $team->id,
        ]));
    }
}
