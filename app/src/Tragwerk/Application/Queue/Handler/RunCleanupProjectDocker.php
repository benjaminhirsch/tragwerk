<?php

declare(strict_types=1);

namespace Tragwerk\Application\Queue\Handler;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Tragwerk\Application\Queue\Message;

use function dirname;

final readonly class RunCleanupProjectDocker
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Message\CleanupProjectDocker $message): void
    {
        $workDir = dirname(__DIR__, 5);

        $this->logger->info('Starting Docker cleanup for deleted project', [
            'project_id' => $message->projectId,
        ]);

        $process = new Process(
            [
                'php',
                'bin/cli',
                'project:docker-cleanup',
                $message->projectId,
                $message->projectSlug,
                $message->host,
                (string) $message->port,
                $message->credentialId,
            ],
            $workDir,
            timeout: 300,
        );

        $process->run(function (string $type, string $buffer) use ($message): void {
            $this->logger->debug('Docker cleanup output', [
                'project_id' => $message->projectId,
                'type'       => $type,
                'output'     => $buffer,
            ]);
        });

        if (! $process->isSuccessful()) {
            $this->logger->error('Docker cleanup failed', [
                'project_id' => $message->projectId,
                'exit_code'  => $process->getExitCode(),
            ]);
        }

        $this->logger->info('Docker cleanup completed', ['project_id' => $message->projectId]);
    }
}
