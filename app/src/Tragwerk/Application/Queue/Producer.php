<?php

declare(strict_types=1);

namespace Tragwerk\Application\Queue;

interface Producer
{
    /** @param int|null $priority 0 is the lowest priority and 9 is the highest priority */
    public function sendMessage(
        Message $message,
        Queue $queue = Queue::DEFAULT,
        int|null $priority = 4,
        int|null $delay = null,
        int|null $timeToLive = null,
    ): void;
}
