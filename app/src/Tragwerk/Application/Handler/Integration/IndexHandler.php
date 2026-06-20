<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Integration;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Repository\ProjectWebhookRepository;

use function assert;

final readonly class IndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectWebhookRepository $webhookRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $activeProject = $request->getAttribute('active_project');
        assert($activeProject instanceof Project);

        $integrations = $this->webhookRepository->findByProject($activeProject->id);

        $uri     = $request->getUri();
        $baseUrl = $uri->getScheme() . '://' . $uri->getHost();

        return $this->renderer->render($request, 'page::integration/index', [
            'project'      => $activeProject,
            'integrations' => $integrations,
            'baseUrl'      => $baseUrl,
        ]);
    }
}
