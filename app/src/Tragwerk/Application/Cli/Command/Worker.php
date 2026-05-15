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
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Tragwerk\Application\Queue\Extension\MessageLoggingExtension;
use Tragwerk\Application\Queue\Processor\MessageProcessor;
use Tragwerk\Application\Queue\Queue;
use Tragwerk\Infrastructure\Queue\Processor\ErrorLoggingProcessor;
use Tragwerk\Infrastructure\Queue\Processor\RequeueingProcessor;
use Tragwerk\Infrastructure\Queue\Processor\TransactionalProcessor;

use function array_map;
use function assert;
use function implode;
use function is_string;
use function sprintf;

#[AsCommand(name: 'worker:start', description: 'Start a worker to consume messages from a given queue')]
final class Worker extends Command
{
    public function __construct(
        private readonly Context $queueContext,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly MessageProcessor $messageProcessor,
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
    }

    /** @throws Exception */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rawQueueName = $input->getArgument('queue-name');
        assert(is_string($rawQueueName));

        $queueName = Queue::tryFrom($rawQueueName);
        if ($queueName === null) {
            $output->writeln(sprintf('<error>Queue `%s` does not exist!</error>', $rawQueueName));
            $output->writeln(sprintf('<info>Valid queues are:</info> %s', implode(
                ', ',
                array_map(
                    static fn (Queue $queue) => $queue->value,
                    Queue::cases(),
                ),
            )));

            return Command::FAILURE;
        }

        $queue = $this->queueContext->createQueue($queueName->value);

        $consumer = new QueueConsumer(
            $this->queueContext,
            new ChainExtension([
                new MessageLoggingExtension($queueName),
                new SignalExtension(),
            ]),
            logger: new ConsoleLogger($output, [
                LogLevel::ERROR => OutputInterface::VERBOSITY_NORMAL,
                LogLevel::INFO => OutputInterface::VERBOSITY_VERBOSE,
                LogLevel::DEBUG => OutputInterface::VERBOSITY_DEBUG,
            ], [
                LogLevel::ERROR => 'error',
                LogLevel::INFO => 'info',
                LogLevel::DEBUG => 'comment',
            ]),
        );

        $processor = $this->messageProcessor;
        $processor = new ErrorLoggingProcessor($this->logger, $processor);
        $processor = new TransactionalProcessor($this->connection, $this->logger, $processor);
        $processor = new RequeueingProcessor($processor);
        $consumer->bind($queue, $processor);

        $consumer->consume();

        return Command::SUCCESS;
    }
}
