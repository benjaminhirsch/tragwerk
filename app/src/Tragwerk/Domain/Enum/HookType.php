<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Enum;

enum HookType: string
{
    case BUILD       = 'build';
    case DEPLOY      = 'deploy';
    case POST_DEPLOY = 'post_deploy';
}
