<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Infrastructure\Cron;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Infrastructure\Cron\SupercronicLogParser;

use function array_map;
use function implode;
use function json_encode;

final class SupercronicLogParserTest extends TestCase
{
    private SupercronicLogParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SupercronicLogParser();
    }

    #[Test]
    public function parsesSucceededRunWithOutput(): void
    {
        $logs = self::lines([
            [
                'msg' => 'starting',
                'time' => '2026-06-27T02:00:00Z',
                'iteration' => 0,
                'job.command' => 'bin/cli app:cleanup',
                'job.position' => 0,
                'job.schedule' => '0 2 * * *',
            ],
            [
                'msg' => 'cleaned 5 items',
                'time' => '2026-06-27T02:00:01Z',
                'channel' => 'stdout',
                'iteration' => 0,
                'job.command' => 'bin/cli app:cleanup',
                'job.position' => 0,
            ],
            [
                'msg' => 'job succeeded',
                'time' => '2026-06-27T02:00:02Z',
                'iteration' => 0,
                'job.command' => 'bin/cli app:cleanup',
                'job.position' => 0,
            ],
        ]);

        $runs = $this->parser->parse($logs);

        self::assertCount(1, $runs);
        self::assertSame('bin/cli app:cleanup', $runs[0]->command);
        self::assertSame('0 2 * * *', $runs[0]->schedule);
        self::assertTrue($runs[0]->succeeded);
        self::assertNotNull($runs[0]->finishedAt);
        self::assertSame('cleaned 5 items', $runs[0]->output);
    }

    #[Test]
    public function parsesFailedRunAndCapturesError(): void
    {
        $logs = self::lines([
            [
                'msg' => 'starting',
                'time' => '2026-06-27T03:00:00Z',
                'iteration' => 2,
                'job.command' => 'bin/cli app:import',
                'job.position' => 1,
            ],
            [
                'level' => 'error',
                'msg' => 'job failed',
                'time' => '2026-06-27T03:00:05Z',
                'error' => 'exit status 1',
                'iteration' => 2,
                'job.command' => 'bin/cli app:import',
                'job.position' => 1,
            ],
        ]);

        $runs = $this->parser->parse($logs);

        self::assertCount(1, $runs);
        self::assertFalse($runs[0]->succeeded);
        self::assertStringContainsString('exit status 1', (string) $runs[0]->output);
    }

    #[Test]
    public function inProgressRunHasNoFinish(): void
    {
        $logs = self::lines([
            [
                'msg' => 'starting',
                'time' => '2026-06-27T04:00:00Z',
                'iteration' => 0,
                'job.command' => 'x',
                'job.position' => 0,
            ],
        ]);

        $runs = $this->parser->parse($logs);

        self::assertCount(1, $runs);
        self::assertNull($runs[0]->finishedAt);
        self::assertNull($runs[0]->succeeded);
    }

    #[Test]
    public function separatesRunsByIteration(): void
    {
        $logs = self::lines([
            [
                'msg' => 'starting',
                'time' => '2026-06-27T02:00:00Z',
                'iteration' => 0,
                'job.command' => 'x',
                'job.position' => 0,
            ],
            [
                'msg' => 'job succeeded',
                'time' => '2026-06-27T02:00:01Z',
                'iteration' => 0,
                'job.command' => 'x',
                'job.position' => 0,
            ],
            [
                'msg' => 'starting',
                'time' => '2026-06-27T02:05:00Z',
                'iteration' => 1,
                'job.command' => 'x',
                'job.position' => 0,
            ],
            [
                'msg' => 'job succeeded',
                'time' => '2026-06-27T02:05:01Z',
                'iteration' => 1,
                'job.command' => 'x',
                'job.position' => 0,
            ],
        ]);

        $runs = $this->parser->parse($logs);

        self::assertCount(2, $runs);
        self::assertSame('2026-06-27 02:00:00', $runs[0]->startedAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-06-27 02:05:00', $runs[1]->startedAt->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function ignoresNonJsonAndCommandlessLines(): void
    {
        $logs = implode("\n", [
            'this is not json',
            (string) json_encode(['level' => 'info', 'msg' => 'supercronic starting']),
            '',
        ]);

        self::assertSame([], $this->parser->parse($logs));
    }

    /** @param list<array<string, mixed>> $records */
    private static function lines(array $records): string
    {
        return implode("\n", array_map(static fn (array $r): string => (string) json_encode($r), $records));
    }
}
