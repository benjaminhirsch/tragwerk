<?php

declare(strict_types=1);

namespace Tragwerk\Application\Response;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface ResponseRenderer
{
    /**
     * @param non-empty-string               $name
     * @param array<non-empty-string, mixed> $params
     */
    public function render(
        ServerRequestInterface $request,
        string $name,
        array $params = [],
        int $statusCode = 200,
    ): ResponseInterface;
}
