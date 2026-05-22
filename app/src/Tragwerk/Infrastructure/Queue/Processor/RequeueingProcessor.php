<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Queue\Processor;

use Enqueue\Consumption\Result;
use Interop\Queue\Context;
use Interop\Queue\Exception;
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\InvalidMessageException;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use Throwable;
use Tragwerk\Application\Queue\Queue;

use function is_int;

final readonly class RequeueingProcessor implements Processor
{
    public function __construct(
        private Context $context,
        private string $queueName,
        private int $maxAttempts,
        private Processor $processor,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @throws InvalidDestinationException
     * @throws Exception
     * @throws InvalidMessageException
     */
    public function process(Message $message, Context $context): object|string
    {
        try {
            return $this->processor->process($message, $context);
        } catch (Throwable $e) {
            $raw     = $message->getProperty('attempt', 0);
            $attempt = is_int($raw) ? $raw : 0;

            if ($attempt < $this->maxAttempts - 1) {
                $retry = $this->context->createMessage(
                    $message->getBody(),
                    $message->getProperties(),
                    $message->getHeaders(),
                );
                $retry->setProperty('attempt', $attempt + 1);

                $this->context->createProducer()->send(
                    $this->context->createQueue($this->queueName),
                    $retry,
                );

                return Result::reject(
                    'Attempt ' . ($attempt + 1) . '/' . $this->maxAttempts . ' failed, requeued: '
                    . $e::class . ': ' . $e->getMessage(),
                );
            }

            $this->context->createProducer()->send(
                $this->context->createQueue(Queue::FAILED->value),
                $message,
            );

            return Result::reject(
                'All ' . $this->maxAttempts . ' attempts exhausted, moved to failed queue: '
                . $e::class . ': ' . $e->getMessage(),
            );
        }
    }
}
