<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ServerRepository;

use function assert;
use function iterator_to_array;

final readonly class IndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectRepository $projectRepository,
        private ServerRepository $serverRepository,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $activeTeam = $request->getAttribute('active_team');

        $projects = $activeTeam instanceof Team
            ? $this->projectRepository->getAll(teamId: $activeTeam->id)
            : $this->projectRepository->getAll();

        $teamId  = $activeTeam instanceof Team ? $activeTeam->id : null;
        $servers = [];
        foreach ($this->serverRepository->getAll(teamId: $teamId) as $server) {
            assert($server instanceof Server);
            $servers[$server->id->toString()] = $server;
        }

        return $this->renderer->render($request, 'page::project/index', [
            'projects' => iterator_to_array($projects),
            'servers'  => iterator_to_array($servers),
        ]);
    }
}
