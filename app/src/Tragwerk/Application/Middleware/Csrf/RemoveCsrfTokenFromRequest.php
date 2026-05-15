<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware\Csrf;

use Fig\Http\Message\RequestMethodInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function is_array;

final readonly class RemoveCsrfTokenFromRequest implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $body = $request->getParsedBody();

            if (is_array($body) && isset($body['csrf_token'])) {
                unset($body['csrf_token']);
                $request = $request->withParsedBody($body);
            }
        }

        return $handler->handle($request);
    }
}
