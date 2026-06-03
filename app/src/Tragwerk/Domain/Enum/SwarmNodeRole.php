<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Enum;

enum SwarmNodeRole: string
{
    case Manager = 'manager';
    case Worker  = 'worker';
}
