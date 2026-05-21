<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Server;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Repository\SetupJobRepository;
use Tragwerk\Domain\ValueObject\ServerIdentifier;

final readonly class IndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ServerRepository $serverRepository,
        private SetupJobRepository $setupJobRepository,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $activeProject = $request->getAttribute('active_project');

        /** @var array<int, Server> $servers */
        $servers = $activeProject instanceof Project
            ? [...$this->serverRepository->getAll(projectId: $activeProject->id)]
            : [];

        /** @var array<int, ServerIdentifier> $serverIds */
        $serverIds = [];
        foreach ($servers as $server) {
            $serverIds[] = $server->id;
        }

        $completedIds = $this->setupJobRepository->getCompletedServerIds($serverIds);

        return $this->renderer->render($request, 'page::server/index', [
            'servers'      => $servers,
            'completedIds' => $completedIds,
        ]);
    }
}
