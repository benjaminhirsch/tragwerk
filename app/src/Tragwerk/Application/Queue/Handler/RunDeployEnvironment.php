<?php

declare(strict_types=1);

namespace Tragwerk\Application\Queue\Handler;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tragwerk\Application\Queue\Message;

use function dirname;
use function sprintf;

final readonly class RunDeployEnvironment
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function handle(Message\DeployEnvironment $message): void
    {
        $projectId = $message->projectId;
        $branch    = $message->branch;
        $commitSha = $message->commitSha;
        $workDir   = dirname(__DIR__, 5);

        $this->logger->info('Starting deploy process', [
            'project_id' => $projectId,
            'branch'     => $branch,
            'commit_sha' => $commitSha,
        ]);

        $deployJobId = $message->deployJobId;

        $process = new Process(
            ['php', 'bin/cli', 'project:deploy', $projectId, $branch, $commitSha, $deployJobId],
            $workDir,
            timeout: 600,
        );

        $process->run(function (string $type, string $buffer) use ($projectId): void {
            $this->logger->debug('Deploy process output', [
                'project_id' => $projectId,
                'type'       => $type,
                'output'     => $buffer,
            ]);
        });

        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                'Deploy process for project %s failed with exit code %d',
                $projectId,
                $process->getExitCode() ?? -1,
            ));
        }

        $this->logger->info('Deploy process completed', [
            'project_id' => $projectId,
            'branch'     => $branch,
        ]);
    }
}
