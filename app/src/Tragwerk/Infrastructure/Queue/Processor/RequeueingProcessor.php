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

final readonly class RequeueingProcessor implements Processor
{
    public function __construct(
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
            $context->createProducer()->send(
                $context->createQueue(Queue::FAILED->value),
                $message,
            );

            return Result::reject($e::class . ': ' . $e->getMessage());
        }
    }
}
