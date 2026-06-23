<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Support;

use Tragwerk\Application\Queue\Message;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Application\Queue\Queue;

/** Test double that records enqueued messages instead of writing them to the queue. */
final class RecordingProducer implements Producer
{
    /** @var list<Message> */
    public array $messages = [];

    public function sendMessage(
        Message $message,
        Queue $queue = Queue::DEFAULT,
        int|null $priority = 4,
        int|null $delay = null,
        int|null $timeToLive = null,
    ): void {
        $this->messages[] = $message;
    }
}
