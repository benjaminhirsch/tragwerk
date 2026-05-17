<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware\Redirect;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class ToServerIndex implements MiddlewareInterface
{
    public function __construct(private UrlHelper $urlHelper)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return new RedirectResponse($this->urlHelper->generate('server'));
    }
}
