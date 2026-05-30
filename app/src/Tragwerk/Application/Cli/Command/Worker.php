<?php

declare(strict_types=1);

namespace Tragwerk\Application\Cli\Command;

use Doctrine\DBAL\Connection;
use Enqueue\Consumption\ChainExtension;
use Enqueue\Consumption\Extension\SignalExtension;
use Enqueue\Consumption\QueueConsumer;
use Exception;
use Interop\Queue\Context;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Tragwerk\Application\Queue\Extension\MessageLoggingExtension;
use Tragwerk\Application\Queue\Processor\MessageProcessor;
use Tragwerk\Application\Queue\Queue;
use Tragwerk\Infrastructure\Queue\Processor\ErrorLoggingProcessor;
use Tragwerk\Infrastructure\Queue\Processor\RequeueingProcessor;
use Tragwerk\Infrastructure\Queue\Processor\TransactionalProcessor;

use function array_map;
use function assert;
use function dirname;
use function explode;
use function implode;
use function is_numeric;
use function is_string;
use function max;
use function pcntl_async_signals;
use function pcntl_signal;
use function rtrim;
use function sprintf;
use function usleep;

use const SIGINT;
use const SIGTERM;

#[AsCommand(name: 'worker:start', description: 'Start a worker to consume messages from a given queue')]
final class Worker extends Command
{
    public function __construct(
        private readonly Context $queueContext,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly MessageProcessor $messageProcessor,
        private readonly int $maxAttempts = 5,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'queue-name',
            InputArgument::REQUIRED,
            'Queue name to consume messages from',
        );
        $this->addOption(
            'workers',
            'w',
            InputOption::VALUE_REQUIRED,
            'Number of worker processes (master mode when > 1)',
            1,
        );
    }

    /** @throws Exception */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rawQueueName = $input->getArgument('queue-name');
        assert(is_string($rawQueueName));

        $queueName = Queue::tryFrom($rawQueueName);
        if ($queueName === null) {
            $output->writeln(sprintf('<error>Queue `%s` does not exist!</error>', $rawQueueName));
            $output->writeln(sprintf(
                '<info>Valid queues are:</info> %s',
                implode(', ', array_map(static fn (Queue $queue) => $queue->value, Queue::cases())),
            ));

            return Command::FAILURE;
        }

        $workersRaw = $input->getOption('workers');
        $numWorkers = max(1, is_numeric($workersRaw) ? (int) $workersRaw : 1);

        if ($numWorkers > 1) {
            return $this->runMaster($queueName, $numWorkers, $output);
        }

        return $this->runSingleWorker($queueName, $output);
    }

    private function runSingleWorker(Queue $queueName, OutputInterface $output): int
    {
        $queue = $this->queueContext->createQueue($queueName->value);

        $consumer = new QueueConsumer(
            $this->queueContext,
            new ChainExtension([
                new MessageLoggingExtension($queueName),
                new SignalExtension(),
            ]),
            logger: new ConsoleLogger($output, [
                LogLevel::ERROR => OutputInterface::VERBOSITY_NORMAL,
                LogLevel::INFO  => OutputInterface::VERBOSITY_VERBOSE,
                LogLevel::DEBUG => OutputInterface::VERBOSITY_DEBUG,
            ], [
                LogLevel::ERROR => 'error',
                LogLevel::INFO  => 'info',
                LogLevel::DEBUG => 'comment',
            ]),
        );

        $processor = $this->messageProcessor;
        $processor = new ErrorLoggingProcessor($this->logger, $processor);
        $processor = new TransactionalProcessor($this->connection, $this->logger, $processor);
        $processor = new RequeueingProcessor($this->queueContext, $queueName->value, $this->maxAttempts, $processor);
        $consumer->bind($queue, $processor);

        $consumer->consume();

        return Command::SUCCESS;
    }

    private function runMaster(Queue $queueName, int $numWorkers, OutputInterface $output): int
    {
        $stopping = false;
        $workDir  = dirname(__DIR__, 5);

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, static function () use (&$stopping): void {
            $stopping = true;
        });
        pcntl_signal(SIGINT, static function () use (&$stopping): void {
            $stopping = true;
        });

        $output->writeln(sprintf(
            '<info>[Master] Starting %d worker(s) for queue "%s"</info>',
            $numWorkers,
            $queueName->value,
        ));

        /** @var Process[] $children */
        $children = [];
        for ($i = 0; $i < $numWorkers; $i++) {
            $children[$i] = $this->spawnChild($queueName, $workDir, $i + 1, $output);
        }

        while (! $stopping) {
            foreach ($children as $slot => $process) {
                $slot = (int) $slot;

                if ($process->isRunning()) {
                    continue;
                }

                $exitCode = $process->getExitCode() ?? -1;
                $output->writeln(sprintf(
                    '<comment>[Master] Worker %d (PID %d) exited with code %d — restarting...</comment>',
                    $slot + 1,
                    $process->getPid() ?? 0,
                    $exitCode,
                ));
                $children[$slot] = $this->spawnChild($queueName, $workDir, $slot + 1, $output);
            }

            usleep(500_000);
        }

        $output->writeln('<info>[Master] Shutdown signal received — stopping workers...</info>');

        foreach ($children as $slot => $process) {
            $slot = (int) $slot;

            if (! $process->isRunning()) {
                continue;
            }

            $output->writeln(sprintf(
                '<comment>[Master] Sending SIGTERM to worker %d (PID %d)...</comment>',
                $slot + 1,
                $process->getPid() ?? 0,
            ));
            $process->signal(SIGTERM);
        }

        foreach ($children as $process) {
            $process->wait();
        }

        $output->writeln('<info>[Master] All workers stopped.</info>');

        return Command::SUCCESS;
    }

    private function spawnChild(Queue $queueName, string $workDir, int $index, OutputInterface $output): Process
    {
        $process = new Process(
            ['php', 'bin/cli', 'worker:start', $queueName->value],
            $workDir,
            timeout: null,
        );

        $process->start(static function (string $type, string $buffer) use ($index, $output): void {
            foreach (explode("\n", rtrim($buffer, "\n")) as $line) {
                if ($line === '') {
                    continue;
                }

                if ($type === Process::ERR) {
                    $output->writeln(sprintf('<error>[Worker %d] %s</error>', $index, $line));

                    continue;
                }

                $output->writeln(sprintf('[Worker %d] %s', $index, $line));
            }
        });

        $output->writeln(sprintf(
            '<info>[Master] Worker %d started (PID %d)</info>',
            $index,
            $process->getPid() ?? 0,
        ));

        return $process;
    }
}
