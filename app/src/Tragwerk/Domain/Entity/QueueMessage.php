<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Entity;

use Tragwerk\Application\Queue\Queue;

use function array_key_exists;
use function assert;
use function basename;
use function is_array;
use function is_string;
use function json_decode;
use function str_replace;

final class QueueMessage
{
    public function __construct(
        public string $id,
        public string $queue,
        public string $body,
        public string $properties,
        public bool $redelivered,
        public int $publishedAt,
        public int|null $priority,
        public string|null $deliveryId,
        public int|null $delayedUntil,
        public int|null $redeliverAfter,
        public int|null $timeToLive,
    ) {
    }

    public function getMessageType(): string
    {
        /** @var array<string, mixed>|null $props */
        $props = json_decode($this->properties, true);
        if (! is_array($props) || ! array_key_exists('type', $props)) {
            return '';
        }

        $type = $props['type'];
        assert(is_string($type));

        return basename(str_replace('\\', '/', $type));
    }

    public function isBeingProcessed(): bool
    {
        return $this->deliveryId !== null;
    }

    public function isFailed(): bool
    {
        return $this->queue === Queue::FAILED->value;
    }
}
