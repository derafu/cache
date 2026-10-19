<?php

declare(strict_types=1);

/**
 * Derafu: Cache - Consistent PSR-6/PSR-16 Cache Wiring Across Derafu Packages.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsCache;

use Derafu\Cache\Memoizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\ItemInterface;

#[CoversClass(Memoizer::class)]
final class MemoizerExpiryTest extends TestCase
{
    public function testPlainPsr6PoolStoresTheValueWithTheRequestedExpiry(): void
    {
        $storage = new ArrayAdapter();
        $pool = new PlainArrayCachePool($storage);
        $memoizer = new Memoizer();
        $calls = 0;
        $compute = static function () use (&$calls): string {
            $calls++;

            return 'computed';
        };
        $before = microtime(true);
        $this->assertSame('computed', $memoizer->remember($pool, 'entry', $compute, ttl: 60));
        $after = microtime(true);
        $this->assertSame('computed', $memoizer->remember(
            $pool,
            'entry',
            static function (): never {
                self::fail('A valid cached value must not be recomputed.');
            },
            ttl: 60
        ));
        $this->assertSame(1, $calls);

        $item = $storage->getItem('entry');
        $this->assertTrue($item->isHit());
        $this->assertInstanceOf(ItemInterface::class, $item);
        $metadata = $item->getMetadata();
        $this->assertArrayHasKey(ItemInterface::METADATA_EXPIRY, $metadata);
        $this->assertGreaterThanOrEqual($before + 60, $metadata[ItemInterface::METADATA_EXPIRY]);
        $this->assertLessThanOrEqual($after + 60, $metadata[ItemInterface::METADATA_EXPIRY]);
    }

    public function testExpiredPlainPsr6EntriesAreRecomputedWithoutWaiting(): void
    {
        $pool = new PlainArrayCachePool(new ArrayAdapter());
        $memoizer = new Memoizer();
        $calls = 0;
        $compute = static function () use (&$calls): int {
            return ++$calls;
        };

        $this->assertSame(1, $memoizer->remember($pool, 'entry', $compute, ttl: -1));
        $this->assertFalse($pool->hasItem('entry'));
        $this->assertSame(2, $memoizer->remember($pool, 'entry', $compute, ttl: -1));
        $this->assertSame(2, $calls);
    }
}

/**
 * Exposes a real Symfony cache through PSR-6 alone, as a consumer's decorator
 * can do. Item storage and expiration remain the real adapter's behavior.
 */
final class PlainArrayCachePool implements CacheItemPoolInterface
{
    public function __construct(private readonly ArrayAdapter $storage)
    {
    }

    public function getItem(string $key): CacheItemInterface
    {
        return $this->storage->getItem($key);
    }

    /** @return iterable<CacheItemInterface> */
    public function getItems(array $keys = []): iterable
    {
        return $this->storage->getItems($keys);
    }

    public function hasItem(string $key): bool
    {
        return $this->storage->hasItem($key);
    }

    public function clear(): bool
    {
        return $this->storage->clear();
    }

    public function deleteItem(string $key): bool
    {
        return $this->storage->deleteItem($key);
    }

    public function deleteItems(array $keys): bool
    {
        return $this->storage->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        return $this->storage->save($item);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->storage->saveDeferred($item);
    }

    public function commit(): bool
    {
        return $this->storage->commit();
    }
}
