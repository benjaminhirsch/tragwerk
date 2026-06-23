<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Environment;

use PHPUnit\Framework\Attributes\Test;
use TragwerkTest\Integration\Support\EnvironmentScopedTestCase;

final class EnvironmentHandlerTest extends EnvironmentScopedTestCase
{
    #[Test]
    public function indexListsBranchesForActiveProject(): void
    {
        $response = $this->dispatch('GET', $this->url('environment'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('main', (string) $response->getBody());
    }

    #[Test]
    public function showRendersForKnownBranch(): void
    {
        $response = $this->dispatch(
            'GET',
            $this->url('environment.show') . '?id=main',
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }
}
