<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Enum;

enum SetupJobStatus: string
{
    case Pending   = 'pending';
    case Running   = 'running';
    case Completed = 'completed';
    case Failed    = 'failed';
}
