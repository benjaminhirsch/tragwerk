<?php

declare(strict_types=1);

namespace Tragwerk\Application\Queue\Extension;

use Enqueue\Consumption\Context\End;
use Enqueue\Consumption\Context\MessageReceived;
use Enqueue\Consumption\Context\PostMessageReceived;
use Enqueue\Consumption\Context\ProcessorException;
use Enqueue\Consumption\Context\Start;
use Enqueue\Consumption\EndExtensionInterface;
use Enqueue\Consumption\MessageReceivedExtensionInterface;
use Enqueue\Consumption\PostMessageReceivedExtensionInterface;
use Enqueue\Consumption\ProcessorExceptionExtensionInterface;
use Enqueue\Consumption\Result;
use Enqueue\Consumption\StartExtensionInterface;
use Psr\Log\LogLevel;
use Stringable;
use Tragwerk\Application\Queue\Queue;

use function is_string;
use function str_replace;

final readonly class MessageLoggingExtension implements
    StartExtensionInterface,
    MessageReceivedExtensionInterface,
    PostMessageReceivedExtensionInterface,
    EndExtensionInterface,
    ProcessorExceptionExtensionInterface
{
    public function __construct(
        private readonly Queue $queueName,
    ) {
    }

    public function onStart(Start $context): void
    {
        $context->getLogger()->info('Consuming messages for queue "{queueName}"', [
            'queueName' => $this->queueName->value,
        ]);
    }

    public function onEnd(End $context): void
    {
        $context->getLogger()->info('Stopped consuming messages for queue "{queueName}"', [
            'queueName' => $this->queueName->value,
        ]);
    }

    public function onMessageReceived(MessageReceived $context): void
    {
        $message = $context->getMessage();

        $message->getProperties()['type'] ?? '';

        $context->getLogger()->info('Received message of type "{type}" from queue "{queueName}"', [
            'type' => $message->getProperties()['type'] ?? '',
            'queueName' => $this->queueName->value,
        ]);

        $context->getLogger()->debug($message->getBody());
    }

    public function onPostMessageReceived(PostMessageReceived $context): void
    {
        $result = $context->getResult();

        $stringResult = null;
        if (is_string($result) || $result instanceof Stringable) {
            $stringResult = (string) $result;
        }

        $logMessage = 'Processed message with result "{result}"';

        $reason = null;
        if ($result instanceof Result && $result->getReason() !== '') {
            $reason      = $result->getReason();
            $logMessage .= ' and reason "{reason}"';
        }

        $logLevel = LogLevel::DEBUG;
        if ($stringResult === Result::REJECT) {
            $logLevel = LogLevel::ERROR;
        }

        $context->getLogger()->log($logLevel, $logMessage, [
            'result' => str_replace('enqueue.', '', $stringResult ?? ''),
            'reason' => $reason,
        ]);
    }

    public function onProcessorException(ProcessorException $context): void
    {
        $e = $context->getException();

        $context->getLogger()->error('Error while processing message: {errorClass}: {errorMessage}', [
            'errorClass' => $e::class,
            'errorMessage' => $e->getMessage(),
        ]);
    }
}
