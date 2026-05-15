<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Queue\Processor;

use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use Psr\Log\LoggerInterface;
use Throwable;
use Tragwerk\Application\Helper\ThrowableHelper;

final readonly class ErrorLoggingProcessor implements Processor
{
    public function __construct(
        private LoggerInterface $logger,
        private Processor $processor,
    ) {
    }

    /** {@inheritDoc}
     *
     * @throws Throwable
     */
    public function process(Message $message, Context $context): object|string
    {
        try {
            return $this->processor->process($message, $context);
        } catch (Throwable $e) {
            $this->logger->error('Failed to process queue message', [
                'exception' => ThrowableHelper::toArray($e),
                'message' => [
                    'id' => $message->getMessageId(),
                    'timestamp' => $message->getTimestamp(),
                    'body' => $message->getBody(),
                    'headers' => $message->getHeaders(),
                    'properties' => $message->getProperties(),
                    'correlationId' => $message->getCorrelationId(),
                    'replyTo' => $message->getReplyTo(),
                ],
            ]);

            throw $e;
        }
    }
}
