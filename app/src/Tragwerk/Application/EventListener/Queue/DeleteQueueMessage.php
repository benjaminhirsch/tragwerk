<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Queue;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\QueueMessageRepository;

final readonly class DeleteQueueMessage
{
    public function __construct(
        private QueueMessageRepository $queueMessageRepository,
    ) {
    }

    public function __invoke(Event\QueueMessageDeleted $event): void
    {
        $this->queueMessageRepository->delete($event->id);
    }
}
