<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Domain;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Repository\DomainRepository;

use function assert;

final readonly class IndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private DomainRepository $domainRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $request->getAttribute('active_project');
        assert($project instanceof Project);

        return $this->renderer->render($request, 'page::domain/index', [
            'domains' => $this->domainRepository->findByProject($project->id),
        ]);
    }
}
