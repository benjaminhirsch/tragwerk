<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Metrics;

use PHPUnit\Framework\Attributes\Test;
use TragwerkTest\Integration\Support\EnvironmentScopedTestCase;

use function json_decode;

final class MetricsHandlerTest extends EnvironmentScopedTestCase
{
    #[Test]
    public function indexRendersForActiveProject(): void
    {
        $response = $this->dispatch('GET', $this->url('metric'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function dataReturnsJsonArray(): void
    {
        $response = $this->dispatch('GET', $this->url('metric.data'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
        self::assertIsArray(json_decode((string) $response->getBody(), true));
    }

    #[Test]
    public function liveRendersErrorStateWithoutCredential(): void
    {
        $response = $this->dispatch('GET', $this->url('metric.live'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }
}
