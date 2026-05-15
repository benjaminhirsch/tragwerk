<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler;

use Mezzio\Authentication\UserInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;

final readonly class HomeHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute(UserInterface::class);

        return $this->renderer->render($request, 'page::home-page', ['user' => $user]);
    }
}
