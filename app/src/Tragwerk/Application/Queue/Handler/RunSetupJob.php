<?php

declare(strict_types=1);

namespace Tragwerk\Application\Queue\Handler;

use Symfony\Component\Process\Process;
use Tragwerk\Application\Queue\Message;

use function dirname;

final readonly class RunSetupJob
{
    public function handle(Message\RunSetupJob $message): void
    {
        $process = new Process(
            ['php', 'bin/cli', 'server:setup', $message->jobId->toString()],
            dirname(__DIR__, 5),
        );
        $process->disableOutput();
        $process->start();
    }
}
