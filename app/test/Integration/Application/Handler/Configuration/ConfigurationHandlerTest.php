<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Configuration;

use PHPUnit\Framework\Attributes\Test;
use TragwerkTest\Integration\Support\EnvironmentScopedTestCase;

final class ConfigurationHandlerTest extends EnvironmentScopedTestCase
{
    #[Test]
    public function indexRendersForActiveProject(): void
    {
        $response = $this->dispatch('GET', $this->url('configuration'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function mountSizesRendersErrorStateWithoutCredential(): void
    {
        // VolumeSizeReader needs SSH; the seeded server has no credential, so the handler
        // must render the fragment with an error rather than 500.
        $response = $this->dispatch('GET', $this->url('configuration.mounts'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }
}
