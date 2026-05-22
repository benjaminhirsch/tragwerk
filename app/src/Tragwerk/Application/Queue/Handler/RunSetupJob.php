<?php

declare(strict_types=1);

namespace Tragwerk\Application\Queue\Handler;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Throwable;
use Tragwerk\Application\Queue\Message;

use function dirname;

final readonly class RunSetupJob
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function handle(Message\RunSetupJob $message): void
    {
        $jobId   = $message->jobId->toString();
        $workDir = dirname(__DIR__, 5);

        $this->logger->info('Spawning setup process', ['job_id' => $jobId]);

        try {
            $process = new Process(
                ['php', 'bin/cli', 'server:setup', $jobId],
                $workDir,
            );
            $process->start(function (string $type, string $buffer) use ($jobId): void {
                $this->logger->debug('Setup process output', [
                    'job_id' => $jobId,
                    'type'   => $type,
                    'output' => $buffer,
                ]);
            });

            $this->logger->info('Setup process started', [
                'job_id' => $jobId,
                'pid'    => $process->getPid(),
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Failed to spawn setup process', [
                'job_id'    => $jobId,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
