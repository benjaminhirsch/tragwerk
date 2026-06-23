<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Legal;

use PHPUnit\Framework\Attributes\Test;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

final class LegalHandlerTest extends AppIntegrationTestCase
{
    #[Test]
    public function imprintRendersWithoutAuthentication(): void
    {
        $response = $this->dispatch('GET', $this->url('imprint'));

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function privacyPolicyRendersWithoutAuthentication(): void
    {
        $response = $this->dispatch('GET', $this->url('privacy-policy'));

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function termsRenderWithoutAuthentication(): void
    {
        $response = $this->dispatch('GET', $this->url('terms-and-conditions'));

        self::assertSame(200, $response->getStatusCode());
    }
}
