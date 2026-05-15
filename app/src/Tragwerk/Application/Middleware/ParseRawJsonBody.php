<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware;

use JsonException;
use Laminas\Diactoros\Response\TextResponse;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function assert;
use function is_array;
use function json_decode;
use function str_starts_with;

use const JSON_THROW_ON_ERROR;

final readonly class ParseRawJsonBody implements MiddlewareInterface
{
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $contentType = $request->getHeaderLine('Content-Type');
        $isJson      = $contentType === 'application/json' || str_starts_with($contentType, 'application/json;');

        if (! $isJson || $request->getParsedBody() !== null) {
            return $handler->handle($request);
        }

        $rawBody = $request->getBody()->__toString();
        $request->getBody()->rewind();

        try {
            $parsedBody = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            assert(is_array($parsedBody));
        } catch (JsonException) {
            return new TextResponse('400 Bad Request: Invalid JSON body', 400);
        }

        $request = $request->withParsedBody($parsedBody);

        return $handler->handle($request);
    }
}
