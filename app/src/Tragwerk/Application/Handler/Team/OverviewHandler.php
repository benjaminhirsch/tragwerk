<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Team;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\ProjectRepository;

use function assert;

final readonly class OverviewHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectRepository $projectRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $activeTeam = $request->getAttribute('active_team');
        assert($activeTeam instanceof Team);

        return $this->renderer->render($request, 'page::team/overview', [
            'projects' => $this->projectRepository->getAll($activeTeam->id),
        ]);
    }
}
