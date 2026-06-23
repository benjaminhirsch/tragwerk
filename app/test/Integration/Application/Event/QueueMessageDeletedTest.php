<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Event;

use Enqueue\Dbal\DbalContext;
use Interop\Queue\Context;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tragwerk\Domain\Event\QueueMessageDeleted;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;
use function is_numeric;

final class QueueMessageDeletedTest extends AppIntegrationTestCase
{
    #[Test]
    public function deletesQueueMessageById(): void
    {
        $context = $this->container->get(Context::class);
        assert($context instanceof DbalContext);
        $context->createDataBaseTable();

        $id = '11111111-1111-1111-1111-111111111111';
        $this->connection->insert('queue_messages', [
            'id'           => $id,
            'published_at' => 0,
            'queue'        => 'default',
        ]);

        $this->dispatcher()->dispatch(new QueueMessageDeleted($id));

        $remaining = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM queue_messages WHERE id = ?',
            [$id],
        );
        assert(is_numeric($remaining));
        self::assertSame(0, (int) $remaining);
    }

    private function dispatcher(): EventDispatcherInterface
    {
        $dispatcher = $this->container->get(EventDispatcherInterface::class);
        assert($dispatcher instanceof EventDispatcherInterface);

        return $dispatcher;
    }
}
