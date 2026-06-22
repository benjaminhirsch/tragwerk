<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Middleware;

use PHPUnit\Framework\Attributes\Test;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

final class AuthenticationMiddlewareTest extends AppIntegrationTestCase
{
    #[Test]
    public function unauthenticatedNormalRequestRedirectsToLogin(): void
    {
        $response = $this->dispatch('GET', $this->url('project'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('login'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function unauthenticatedHtmxRequestReturns200WithHxRedirectHeader(): void
    {
        $response = $this->dispatch(
            'GET',
            $this->url('project'),
            headers: ['HX-Request' => 'true'],
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($this->url('login'), $response->getHeaderLine('HX-Redirect'));
    }

    #[Test]
    public function unauthenticatedHtmxPollingRequestReturns200WithHxRedirectHeader(): void
    {
        $response = $this->dispatch(
            'GET',
            $this->url('container.status'),
            headers: ['HX-Request' => 'true'],
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($this->url('login'), $response->getHeaderLine('HX-Redirect'));
        self::assertEmpty((string) $response->getBody());
    }
}
