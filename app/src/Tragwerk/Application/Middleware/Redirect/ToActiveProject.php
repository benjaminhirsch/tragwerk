<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware\Redirect;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Project;

final readonly class ToActiveProject implements MiddlewareInterface
{
    public function __construct(
        private UrlHelper $urlHelper,
        private ResponseRenderer $responseRenderer,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $project = $request->getAttribute('active_project');

        if (! $project instanceof Project) {
            return $this->responseRenderer->render($request, 'page::error/404', [], 404);
        }

        return new RedirectResponse($this->urlHelper->generate('project.show', [
            'id' => $project->id,
        ]));
    }
}
