<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Enum;

enum BuildLogType: string
{
    case PUSH    = 'PUSH';
    case WEBHOOK = 'WEBHOOK';
    case CRONJOB = 'CRONJOB';
    case SYSTEM  = 'SYSTEM';
}
