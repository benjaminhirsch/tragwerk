<?php

declare(strict_types=1);

namespace Tragwerk\Application\Helper;

use Psr\Http\Message\ServerRequestInterface;

final readonly class RequestHelper
{
    private function __construct()
    {
    }

    public static function isHtmxRequest(ServerRequestInterface $request): bool
    {
        return $request->getHeaderLine('HX-Request') === 'true';
    }
}
