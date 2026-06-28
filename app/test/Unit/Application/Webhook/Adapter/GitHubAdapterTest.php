<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Webhook\Adapter;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Tragwerk\Application\Webhook\Adapter\GitHubAdapter;

final class GitHubAdapterTest extends TestCase
{
    private const string ZERO_SHA = '0000000000000000000000000000000000000000';

    private GitHubAdapter $adapter;
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->adapter = new GitHubAdapter();
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function normalPushYieldsNonDeletedPayload(): void
    {
        $payload = $this->adapter->extractPushPayload($this->request([
            'ref'   => 'refs/heads/feature/login',
            'after' => 'abc123',
        ]));

        self::assertNotNull($payload);
        self::assertSame('feature/login', $payload->branch);
        self::assertSame('abc123', $payload->commitSha);
        self::assertFalse($payload->deleted);
    }

    #[Test]
    public function zeroShaYieldsDeletedPayload(): void
    {
        $payload = $this->adapter->extractPushPayload($this->request([
            'ref'   => 'refs/heads/feature/login',
            'after' => self::ZERO_SHA,
        ]));

        self::assertNotNull($payload);
        self::assertSame('feature/login', $payload->branch);
        self::assertSame('', $payload->commitSha);
        self::assertTrue($payload->deleted);
    }

    #[Test]
    public function nonBranchRefIsIgnored(): void
    {
        $payload = $this->adapter->extractPushPayload($this->request([
            'ref'   => 'refs/tags/v1.0.0',
            'after' => 'abc123',
        ]));

        self::assertNull($payload);
    }

    /** @param array<string, mixed> $body */
    private function request(array $body): ServerRequestInterface
    {
        return $this->factory->createServerRequest('POST', '/webhooks/forge/github')
            ->withParsedBody($body);
    }
}
