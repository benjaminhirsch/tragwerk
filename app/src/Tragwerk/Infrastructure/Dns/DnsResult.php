<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Dns;

readonly class DnsResult
{
    private function __construct(
        public DnsResolution $status,
        public string|null $ip,
    ) {
    }

    public static function resolved(string $ip): self
    {
        return new self(DnsResolution::RESOLVED, $ip);
    }

    public static function notFound(): self
    {
        return new self(DnsResolution::NOT_FOUND, null);
    }

    public static function unreachable(): self
    {
        return new self(DnsResolution::UNREACHABLE, null);
    }
}
