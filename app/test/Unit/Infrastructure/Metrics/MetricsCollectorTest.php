<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Infrastructure\Metrics;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tragwerk\Application\Service\Credential\CredentialEncryptor;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Infrastructure\Metrics\MetricsCollector;
use Tragwerk\Infrastructure\Ssh\RemoteShell;

final class MetricsCollectorTest extends TestCase
{
    private MetricsCollector $collector;

    protected function setUp(): void
    {
        // parse() never touches the shell, so a real (stateless) RemoteShell is fine here.
        $this->collector = new MetricsCollector(
            new RemoteShell(new CredentialEncryptor('NKvFeNFUeEx4Lvifq6TVfYyIqvuiNrUl8kvWqboSMhQ=')),
        );
    }

    private const string OUTPUT = <<<'TXT'
        cpu  100 0 50 800 10 0 5 0 0 0
        cpu  110 0 55 840 12 0 6 0 0 0
        LOAD 0.42
        MEM 8589934592 4294967296
        DISK 50000000000 12000000000
        TXT;

    #[Test]
    public function parsesAllHostMetrics(): void
    {
        $sample = $this->collector->parse(ServerIdentifier::create(), self::OUTPUT);

        // totalDelta = 1023-965 = 58, idleDelta = 852-810 = 42 → busy = 1 - 42/58 ≈ 0.27586
        self::assertEqualsWithDelta(27.586, $sample->cpuPercent, 0.01);
        self::assertSame(0.42, $sample->load1);
        self::assertSame(8589934592, $sample->memTotalBytes);
        self::assertSame(4294967296, $sample->memUsedBytes);
        self::assertSame(50000000000, $sample->diskTotalBytes);
        self::assertSame(12000000000, $sample->diskUsedBytes);
    }

    #[Test]
    public function cpuPercentIsZeroWhenNoDelta(): void
    {
        $output = <<<'TXT'
            cpu  100 0 50 800 10 0 5 0 0 0
            cpu  100 0 50 800 10 0 5 0 0 0
            LOAD 0.00
            MEM 1024 512
            DISK 2048 1024
            TXT;

        $sample = $this->collector->parse(ServerIdentifier::create(), $output);

        self::assertSame(0.0, $sample->cpuPercent);
    }

    #[Test]
    public function throwsOnIncompleteOutput(): void
    {
        $this->expectException(RuntimeException::class);

        // Only one cpu line → cannot compute a delta.
        $this->collector->parse(ServerIdentifier::create(), "cpu  1 2 3 4\nLOAD 0.1\nMEM 1 1\nDISK 1 1");
    }
}
