<?php

declare(strict_types=1);

namespace Tragwerk\Application\Queue\Handler;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tragwerk\Application\Queue\Message;

use function dirname;
use function sprintf;

final readonly class RunSetupJob
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function handle(Message\RunSetupJob $message): void
    {
        $jobId   = $message->jobId->toString();
        $workDir = dirname(__DIR__, 5);

        $this->logger->info('Starting setup process', ['job_id' => $jobId]);

        $process = new Process(
            ['php', 'bin/cli', 'server:setup', $jobId],
            $workDir,
            timeout: 900,
        );

        $process->run(function (string $type, string $buffer) use ($jobId): void {
            $this->logger->debug('Setup process output', [
                'job_id' => $jobId,
                'type'   => $type,
                'output' => $buffer,
            ]);
        });

        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                'Setup process for job %s failed with exit code %d',
                $jobId,
                $process->getExitCode() ?? -1,
            ));
        }

        $this->logger->info('Setup process completed', ['job_id' => $jobId]);
    }
}
