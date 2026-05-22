<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Team;

use Mezzio\Authentication\UserInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;

final readonly class IndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private TeamRepository $teamRepository,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute(UserInterface::class);
        assert($user instanceof UserInterface);

        return $this->renderer->render($request, 'page::team/index', [
            'teams' => $this->teamRepository->getAll(
                ownerIds: [UserIdentifier::fromString($user->getIdentity())],
            ),
        ]);
    }
}
