<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Enum;

enum RouteType: string
{
    case UPSTREAM = 'upstream';
    case REDIRECT = 'redirect';
}
