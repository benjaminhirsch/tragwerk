<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Log;

use PHPUnit\Framework\Attributes\Test;
use TragwerkTest\Integration\Support\EnvironmentScopedTestCase;

final class LogHandlerTest extends EnvironmentScopedTestCase
{
    #[Test]
    public function indexRendersForActiveEnvironment(): void
    {
        $response = $this->dispatch('GET', $this->url('log'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function tailRendersErrorStateWithoutCredential(): void
    {
        $response = $this->dispatch('GET', $this->url('log.tail'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }
}
