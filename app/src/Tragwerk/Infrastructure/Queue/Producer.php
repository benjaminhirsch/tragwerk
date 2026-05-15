<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Queue;

use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\NormalizerBuilder;
use Interop\Queue\Context;
use Throwable;
use Tragwerk\Application\Exception\UnableToSendQueueMessage;
use Tragwerk\Application\Queue\Message;
use Tragwerk\Application\Queue\Queue;

final readonly class Producer implements \Tragwerk\Application\Queue\Producer
{
    public function __construct(
        private Context $context,
        private NormalizerBuilder $normalizerBuilder,
    ) {
    }

    public function sendMessage(
        Message $message,
        Queue $queue = Queue::DEFAULT,
        int|null $priority = 4,
        int|null $delay = null,
        int|null $timeToLive = null,
    ): void {
        try {
            $rawMessageData = $this->normalizerBuilder->normalizer(Format::json())->normalize($message);

            $envelopedMessage = $this->context->createMessage($rawMessageData);
            $envelopedMessage->setProperty('type', $message::class);

            $this->context->createProducer()
                ->setPriority($priority)
                ->setDeliveryDelay($delay)
                ->setTimeToLive($timeToLive)
                ->send(
                    $this->context->createQueue($queue->value),
                    $envelopedMessage,
                );
        } catch (Throwable $e) {
            throw new UnableToSendQueueMessage($e->getMessage(), previous: $e);
        }
    }
}
