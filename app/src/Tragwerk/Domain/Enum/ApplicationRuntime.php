<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Enum;

enum ApplicationRuntime: string
{
    case PHP82 = 'php:8.2';
    case PHP83 = 'php:8.3';
    case PHP84 = 'php:8.4';
    case PHP85 = 'php:8.5';
}
