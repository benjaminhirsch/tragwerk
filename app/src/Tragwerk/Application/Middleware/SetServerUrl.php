<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware;

use Mezzio\Helper\ServerUrlHelper;
use Override;
//use Platformsh\ConfigReader\Config;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SetServerUrl implements MiddlewareInterface
{
    public function __construct(
        private readonly ServerUrlHelper $serverUrl,
    ) {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestUri = $request->getUri();

        /*$config = new Config();
         * if ($config->inRuntime()) {
         * $rawRouteUri = $config->getRoute('app')['url'];
         * $routeUri    = new Uri($rawRouteUri);
         * $requestUri  = $requestUri
         * ->withScheme($routeUri->getScheme())
         * ->withHost($routeUri->getHost())
         * ->withPort($routeUri->getPort());
         * }*/

        $this->serverUrl->setUri($requestUri);

        return $handler->handle($request);
    }
}
