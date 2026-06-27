<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\Config;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Config\CronScheduleValidator;

final class CronScheduleValidatorTest extends TestCase
{
    private CronScheduleValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new CronScheduleValidator();
    }

    #[Test]
    #[DataProvider('validSchedules')]
    public function acceptsValidSchedules(string $schedule): void
    {
        self::assertTrue($this->validator->isValid($schedule), $schedule);
    }

    #[Test]
    #[DataProvider('invalidSchedules')]
    public function rejectsInvalidSchedules(string $schedule): void
    {
        self::assertFalse($this->validator->isValid($schedule), $schedule);
    }

    /** @return Generator<string, array{string}> */
    public static function validSchedules(): Generator
    {
        yield 'every minute'       => ['* * * * *'];
        yield 'daily at 2'         => ['0 2 * * *'];
        yield 'step minutes'       => ['*/15 * * * *'];
        yield 'list'               => ['0,15,30,45 * * * *'];
        yield 'range'              => ['0 9-17 * * *'];
        yield 'range with step'    => ['0 0-23/2 * * *'];
        yield 'month names'        => ['0 0 1 JAN,JUN *'];
        yield 'weekday names'      => ['0 0 * * MON-FRI'];
        yield 'sunday as 7'        => ['0 0 * * 7'];
        yield 'six fields seconds' => ['30 0 2 * * *'];
        yield 'descriptor hourly'  => ['@hourly'];
        yield 'descriptor daily'   => ['@daily'];
        yield 'every duration'     => ['@every 1h30m'];
        yield 'dow hash'           => ['0 0 * * 1#2'];
        yield 'dom last'           => ['0 0 L * *'];
        yield 'question mark'      => ['0 0 ? * MON'];
    }

    /** @return Generator<string, array{string}> */
    public static function invalidSchedules(): Generator
    {
        yield 'empty'             => [''];
        yield 'whitespace'        => ['   '];
        yield 'too few fields'    => ['* * * *'];
        yield 'too many fields'   => ['* * * * * * *'];
        yield 'all out of range'  => ['99 99 99 99 99'];
        yield 'minute too high'   => ['61 * * * *'];
        yield 'hour too high'     => ['0 24 * * *'];
        yield 'month too high'    => ['0 0 1 13 *'];
        yield 'dom zero'          => ['0 0 0 * *'];
        yield 'step zero'         => ['*/0 * * * *'];
        yield 'garbage words'     => ['foo bar baz qux quux'];
        yield 'unknown month'     => ['0 0 1 FOO *'];
        yield 'inverted range'    => ['0 17-9 * * *'];
        yield 'bad descriptor'    => ['@reboot'];
        yield 'every no unit'     => ['@every 5'];
        yield 'trailing comma'    => ['0,15, * * * *'];
    }
}
