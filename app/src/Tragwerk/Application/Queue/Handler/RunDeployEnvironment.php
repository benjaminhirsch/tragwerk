<?php

declare(strict_types=1);

namespace Tragwerk\Application\Queue\Handler;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Process\Process;
use Tragwerk\Application\Queue\Message;

use function dirname;
use function sprintf;

final readonly class RunDeployEnvironment
{
    public function __construct(
        private LoggerInterface $logger,
        private LockFactory $lockFactory,
    ) {
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
        $acmeEmail   = $message->acmeEmail;

        $lock = $this->lockFactory->createLock('deploy:' . $projectId . ':' . $branch, ttl: 600.0);
        $lock->acquire(blocking: true);

        try {
            $process = new Process(
                ['php', 'bin/cli', 'project:deploy', $projectId, $branch, $commitSha, $deployJobId, $acmeEmail],
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
        } finally {
            $lock->release();
        }
    }
}
