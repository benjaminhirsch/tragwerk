<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Support;

use Tragwerk\Application\Queue\Message;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Application\Queue\Queue;

final class NullProducer implements Producer
{
    public function sendMessage(
        Message $message,
        Queue $queue = Queue::DEFAULT,
        int|null $priority = 4,
        int|null $delay = null,
        int|null $timeToLive = null,
    ): void {
        // no-op: suppresses queue I/O in integration tests
    }
}
