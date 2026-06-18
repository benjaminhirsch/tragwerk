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
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\TeamRepository;

final readonly class ToActiveTeam implements MiddlewareInterface
{
    public function __construct(
        private UrlHelper $urlHelper,
        private TeamRepository $teamRepository,
        private ResponseRenderer $responseRenderer,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $team = $request->getAttribute('active_team');

        if (! $team instanceof Team) {
            return $this->responseRenderer->render($request, 'page::error/404', [], 404);
        }

        return new RedirectResponse($this->urlHelper->generate('team.show', [
            'id' => $team->id,
        ]));
    }
}
