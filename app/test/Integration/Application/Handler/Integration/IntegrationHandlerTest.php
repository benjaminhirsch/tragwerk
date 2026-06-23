<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Integration;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\ProjectWebhook;
use Tragwerk\Domain\Enum\GitForge;
use Tragwerk\Domain\Repository\ProjectWebhookRepository;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\WebhookIntegrationIdentifier;
use TragwerkTest\Integration\Support\EnvironmentScopedTestCase;

use function assert;

final class IntegrationHandlerTest extends EnvironmentScopedTestCase
{
    #[Test]
    public function indexRendersForActiveProject(): void
    {
        $response = $this->dispatch('GET', $this->url('integration'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createGetRendersForm(): void
    {
        $response = $this->dispatch('GET', $this->url('integration.create'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostPersistsWebhookAndRedirects(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('integration.create'),
            ['forge' => GitForge::GITHUB->value],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('integration'), $response->getHeaderLine('Location'));

        $repo = $this->container->get(ProjectWebhookRepository::class);
        assert($repo instanceof ProjectWebhookRepository);
        self::assertCount(1, $repo->findByProject($this->project->id));
    }

    #[Test]
    public function deleteRemovesWebhookAndRedirects(): void
    {
        $webhook = $this->seedWebhook();

        $response = $this->dispatch(
            'POST',
            $this->url('integration.delete', ['id' => $webhook->id->toString()]),
            [],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());

        $repo = $this->container->get(ProjectWebhookRepository::class);
        assert($repo instanceof ProjectWebhookRepository);
        self::assertCount(0, $repo->findByProject($this->project->id));
    }

    private function seedWebhook(): ProjectWebhook
    {
        $webhook = new ProjectWebhook(
            WebhookIntegrationIdentifier::create(),
            $this->project->id,
            GitForge::GITHUB,
            'secret-token',
            TimestampImmutable::now(),
        );

        $repo = $this->container->get(ProjectWebhookRepository::class);
        assert($repo instanceof ProjectWebhookRepository);
        $repo->create($webhook);

        return $webhook;
    }
}
