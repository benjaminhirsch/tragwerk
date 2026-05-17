<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;

final readonly class IndexHandler implements RequestHandlerInterface
{
    public function __construct(private ResponseRenderer $renderer)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->renderer->render($request, 'page::project/index');
    }
}
