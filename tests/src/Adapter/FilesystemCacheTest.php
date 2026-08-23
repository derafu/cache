<?php

declare(strict_types=1);

/**
 * Derafu: Cache - Consistent PSR-6/PSR-16 Cache Wiring Across Derafu Packages.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsCache\Adapter;

use Derafu\Cache\Adapter\FilesystemCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use stdClass;

#[CoversClass(FilesystemCache::class)]
class FilesystemCacheTest extends TestCase
{
    private string $directory;

    private FilesystemCache $pool;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/derafu_cache_test_' . uniqid();
        $this->pool = new FilesystemCache('test', $this->directory);
    }

    protected function tearDown(): void
    {
        $this->pool->clear();
    }

    public function testIsAPsr6CacheItemPool(): void
    {
        $this->assertInstanceOf(CacheItemPoolInterface::class, $this->pool);
    }

    public function testSupportsObjectsUnlikePhpFilesCache(): void
    {
        $object = new stdClass();
        $object->name = 'folios';

        $item = $this->pool->getItem('example_key');
        $item->set($object);
        $this->pool->save($item);

        $reopened = new FilesystemCache('test', $this->directory);
        $reopenedItem = $reopened->getItem('example_key');
        $reopenedObject = $reopenedItem->get();

        // Deserialized from disk, so it's a different instance by design —
        // assertSame() on the object itself would fail regardless of the
        // adapter behaving correctly, hence comparing the property instead.
        $this->assertTrue($reopenedItem->isHit());
        $this->assertInstanceOf(stdClass::class, $reopenedObject);
        $this->assertSame('folios', $reopenedObject->name);
    }

    public function testMissingItemIsNotAHit(): void
    {
        $this->assertFalse($this->pool->getItem('never_saved')->isHit());
    }
}
