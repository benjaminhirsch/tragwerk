<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Enum;

enum MountSource: string
{
    case LOCAL   = 'local';
    case SERVICE = 'service';
}
