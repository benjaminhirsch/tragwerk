<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Dto\Server;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Dto\Server\Server;
use Tragwerk\Application\Exception\ValidationCollection;

final class ServerTest extends TestCase
{
    #[Test]
    public function validIpv4Constructs(): void
    {
        $dto = new Server('web-01', '203.0.113.10', 22);

        self::assertSame('web-01', $dto->name);
        self::assertSame('203.0.113.10', $dto->host);
        self::assertSame(22, $dto->port);
    }

    #[Test]
    public function validIpv6Constructs(): void
    {
        $dto = new Server('web-01', '2001:db8::1');

        self::assertSame('2001:db8::1', $dto->host);
    }

    #[Test]
    public function emptyNameIsRejected(): void
    {
        self::assertContains('name', $this->errorFields('', '203.0.113.10', 22));
    }

    #[Test]
    public function nonIpHostIsRejected(): void
    {
        self::assertContains('host', $this->errorFields('web', 'example.com', 22));
    }

    #[Test]
    public function portOutOfRangeIsRejected(): void
    {
        self::assertContains('port', $this->errorFields('web', '203.0.113.10', 70000));
        self::assertContains('port', $this->errorFields('web', '203.0.113.10', 0));
    }

    /** @return list<string> */
    private function errorFields(string $name, string $host, int $port): array
    {
        try {
            new Server($name, $host, $port);
        } catch (ValidationCollection $e) {
            return array_map(static fn ($v) => $v->name, $e->validations);
        }

        self::fail('Expected ValidationCollection');
    }
}
