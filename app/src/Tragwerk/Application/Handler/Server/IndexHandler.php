<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Server;

use Generator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Repository\ServerRepository;

final readonly class IndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ServerRepository $serverRepository,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $activeProject = $request->getAttribute('active_project');

        $servers = $activeProject instanceof Project
            ? $this->serverRepository->getAll(projectId: $activeProject->id)
            : (static function (): Generator {
                yield from [];
            })();

        return $this->renderer->render($request, 'page::server/index', ['servers' => $servers]);
    }
}
