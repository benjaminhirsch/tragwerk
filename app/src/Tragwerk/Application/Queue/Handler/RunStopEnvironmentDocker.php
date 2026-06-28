<?php

declare(strict_types=1);

namespace Tragwerk\Application\Queue\Handler;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Tragwerk\Application\Queue\Message;

use function dirname;

final readonly class RunStopEnvironmentDocker
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Message\StopEnvironmentDocker $message): void
    {
        $workDir = dirname(__DIR__, 5);

        $this->logger->info('Stopping Docker containers for environment', [
            'project_id' => $message->projectId,
            'branch'     => $message->branch,
        ]);

        $process = new Process(
            [
                'php',
                'bin/cli',
                'environment:docker-stop',
                $message->projectId,
                $message->branch,
                $message->host,
                (string) $message->port,
                $message->credentialId,
            ],
            $workDir,
            timeout: 300,
        );

        $process->run(function (string $type, string $buffer) use ($message): void {
            $this->logger->debug('Environment Docker stop output', [
                'project_id' => $message->projectId,
                'branch'     => $message->branch,
                'type'       => $type,
                'output'     => $buffer,
            ]);
        });

        if (! $process->isSuccessful()) {
            $this->logger->error('Environment Docker stop failed', [
                'project_id' => $message->projectId,
                'branch'     => $message->branch,
                'exit_code'  => $process->getExitCode(),
            ]);
        }

        $this->logger->info('Environment Docker stop completed', [
            'project_id' => $message->projectId,
            'branch'     => $message->branch,
        ]);
    }
}
