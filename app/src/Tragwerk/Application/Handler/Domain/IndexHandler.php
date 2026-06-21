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
use function is_string;

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

        $branch = $request->getAttribute('active_environment');
        assert(is_string($branch));

        return $this->renderer->render($request, 'page::domain/index', [
            'branch'  => $branch,
            'domains' => $this->domainRepository->findByEnvironment($project->id, $branch),
        ]);
    }
}
