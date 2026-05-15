<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Queue\Processor;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use Psr\Log\LoggerInterface;
use Throwable;

class TransactionalProcessor implements Processor
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly Processor $processor,
    ) {
    }

    /** {@inheritDoc}
     *
     * @throws Exception
     * @throws Throwable
     */
    public function process(Message $message, Context $context): object|string
    {
        if ($this->connection->isTransactionActive()) {
            $this->logger->warning('Detected active DB transaction at beginning of queue job - rolling back');

            $this->connection->rollBack();
        }

        $this->connection->beginTransaction();

        try {
            $result = $this->processor->process($message, $context);

            $this->connection->commit();

            return $result;
        } catch (Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }
    }
}
