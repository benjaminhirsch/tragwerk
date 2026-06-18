<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Environment;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;

final readonly class ShowHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->renderer->render($request, 'page::environment/show');
    }
}
