<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\ProjectRepository;

final readonly class IndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectRepository $projectRepository,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $activeTeam = $request->getAttribute('active_team');

        $projects = $activeTeam instanceof Team
            ? $this->projectRepository->getAll(teamId: $activeTeam->id)
            : $this->projectRepository->getAll();

        return $this->renderer->render($request, 'page::project/index', ['projects' => $projects]);
    }
}
