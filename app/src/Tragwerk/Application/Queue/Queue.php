<?php

declare(strict_types=1);

namespace Tragwerk\Application\Queue;

enum Queue: string
{
    case DEFAULT = 'default';
    case FAILED  = 'failed';
}
