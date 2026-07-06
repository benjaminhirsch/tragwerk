<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Dns;

enum DnsResolution
{
    /** An IPv4 address was resolved. */
    case RESOLVED;

    /** A resolver answered, but there is no A record for the host (NXDOMAIN / no A). */
    case NOT_FOUND;

    /** No resolver could be reached (network error / outbound DNS blocked). */
    case UNREACHABLE;
}
