<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Environment;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Infrastructure\Git\BareRepository;

use function assert;

final readonly class IndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private BareRepository $bareRepository,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $activeProject = $request->getAttribute('active_project');
        assert($activeProject instanceof Project);

        return $this->renderer->render($request, 'page::environment/index', [
            'branches' => $this->bareRepository->getBranches($activeProject->id->toString()),
        ]);
    }
}
