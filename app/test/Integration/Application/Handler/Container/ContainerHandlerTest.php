<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Container;

use PHPUnit\Framework\Attributes\Test;
use TragwerkTest\Integration\Support\EnvironmentScopedTestCase;

final class ContainerHandlerTest extends EnvironmentScopedTestCase
{
    #[Test]
    public function indexRendersForActiveEnvironment(): void
    {
        $response = $this->dispatch('GET', $this->url('container'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function statusRendersErrorStateWhenServerHasNoCredential(): void
    {
        // No credential is assigned to the seeded server, so the SSH fetch fails and the
        // handler must render the status fragment with an error rather than 500.
        $response = $this->dispatch('GET', $this->url('container.status'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function indexRedirectsUnauthenticatedUserToLogin(): void
    {
        $response = $this->dispatch('GET', $this->url('container'));

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/login', $response->getHeaderLine('Location'));
    }
}
