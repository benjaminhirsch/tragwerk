<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Credential;

use Generator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Repository\CredentialRepository;

final readonly class IndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private CredentialRepository $credentialRepository,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $activeProject = $request->getAttribute('active_project');

        $credentials = $activeProject instanceof Project
            ? $this->credentialRepository->getAll(projectId: $activeProject->id)
            : (static function (): Generator {
                yield from [];
            })();

        return $this->renderer->render($request, 'page::credential/index', ['credentials' => $credentials]);
    }
}
