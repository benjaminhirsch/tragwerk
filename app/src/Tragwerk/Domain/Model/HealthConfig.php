<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

/**
 * How long a freshly started container may take before the deployment gives up.
 *
 * Deploy hooks run in the entrypoint before the web server starts listening, so a
 * hook doing heavy work keeps the container unhealthy for its whole duration. The
 * defaults mirror what the compose generator emitted before this was configurable.
 */
final readonly class HealthConfig
{
    public const string DEFAULT_START_PERIOD = '30s';
    public const int DEFAULT_RETRIES         = 12;

    public function __construct(
        public string $startPeriod = self::DEFAULT_START_PERIOD,
        public int $retries = self::DEFAULT_RETRIES,
    ) {
    }
}
