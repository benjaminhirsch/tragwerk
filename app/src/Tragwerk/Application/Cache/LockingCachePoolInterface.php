<?php

declare(strict_types=1);

namespace Tragwerk\Application\Cache;

use Override;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

final class LockingCachePoolInterface implements CacheItemPoolInterface
{
    /** @var array<array-key, SharedLockInterface>  */
    private array $activeLocks = [];

    public function __construct(
        private readonly CacheItemPoolInterface $pool,
        private readonly LockFactory $lockFactory,
    ) {
    }

    #[Override]
    public function getItem(string $key): CacheItemInterface
    {
        $this->acquireLock($key);

        return $this->pool->getItem($key);
    }

    /**
     * @param string[] $keys
     *
     * @return iterable<CacheItemInterface>
     *
     * @throws InvalidArgumentException
     */
    #[Override]
    public function getItems(array $keys = []): iterable
    {
        foreach ($keys as $key) {
            $this->acquireLock($key);
        }

        // @phpstan-ignore return.type
        return $this->pool->getItems($keys);
    }

    #[Override]
    public function hasItem(string $key): bool
    {
        return $this->pool->hasItem($key);
    }

    #[Override]
    public function clear(): bool
    {
        $this->releaseAllLocks();

        return $this->pool->clear();
    }

    #[Override]
    public function deleteItem(string $key): bool
    {
        $result = $this->pool->deleteItem($key);
        $this->releaseLock($key);

        return $result;
    }

    /**
     * @param string[] $keys
     *
     * @throws InvalidArgumentException
     */
    #[Override]
    public function deleteItems(array $keys): bool
    {
        $result = $this->pool->deleteItems($keys);
        foreach ($keys as $key) {
            $this->releaseLock($key);
        }

        return $result;
    }

    #[Override]
    public function save(CacheItemInterface $item): bool
    {
        $result = $this->pool->save($item);
        $this->releaseLock($item->getKey());

        return $result;
    }

    #[Override]
    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->pool->saveDeferred($item);
    }

    #[Override]
    public function commit(): bool
    {
        $result = $this->pool->commit();
        $this->releaseAllLocks();

        return $result;
    }

    private function acquireLock(string $key): void
    {
        if (isset($this->activeLocks[$key])) {
            return;
        }

        $lock = $this->lockFactory->createLock('sess_' . $key, 30.0); // 30s Auto-Release TTL
        $lock->acquire(true); // true = Blocking Mode
        $this->activeLocks[$key] = $lock;
    }

    private function releaseLock(string $key): void
    {
        if (! isset($this->activeLocks[$key])) {
            return;
        }

        $this->activeLocks[$key]->release();
        unset($this->activeLocks[$key]);
    }

    private function releaseAllLocks(): void
    {
        foreach ($this->activeLocks as $lock) {
            $lock->release();
        }

        $this->activeLocks = [];
    }

    public function __destruct()
    {
        $this->releaseAllLocks();
    }
}
