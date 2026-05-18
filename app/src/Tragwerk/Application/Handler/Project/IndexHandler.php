<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project;

use Mezzio\Authentication\UserInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;

final readonly class IndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectRepository $projectRepository,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute(UserInterface::class);
        assert($user instanceof UserInterface);

        return $this->renderer->render($request, 'page::project/index', [
            'projects' => $this->projectRepository->getAll(
                ownerIds: [UserIdentifier::fromString($user->getIdentity())],
            ),
        ]);
    }
}
