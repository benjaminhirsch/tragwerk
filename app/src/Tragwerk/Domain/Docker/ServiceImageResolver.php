<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Docker;

use Tragwerk\Domain\Enum\ApplicationRuntime;
use Tragwerk\Domain\Enum\ServiceRuntime;

use function str_replace;
use function str_starts_with;
use function substr;

final class ServiceImageResolver
{
    public function forService(ServiceRuntime $runtime): string
    {
        if (str_starts_with($runtime->value, 'postgresql:')) {
            return 'postgres:' . substr($runtime->value, 11);
        }

        if (str_starts_with($runtime->value, 'valkey:')) {
            return 'valkey/valkey:' . substr($runtime->value, 7);
        }

        return $runtime->value;
    }

    public function forApplication(ApplicationRuntime $runtime): string
    {
        return 'dunglas/frankenphp:' . str_replace(':', '', $runtime->value);
    }
}
