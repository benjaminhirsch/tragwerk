<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware\Conditional;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Domain\Entity\Project;

final readonly class RequiresActiveProject implements MiddlewareInterface
{
    public function __construct(
        private MiddlewareInterface $middleware,
    ) {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $activeProject = $request->getAttribute('active_project');
        if (! $activeProject instanceof Project) {
            return $handler->handle($request);
        }

        return $this->middleware->process($request, $handler);
    }
}
