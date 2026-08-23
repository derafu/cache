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

use Derafu\Cache\Adapter\PhpFilesCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;

#[CoversClass(PhpFilesCache::class)]
class PhpFilesCacheTest extends TestCase
{
    private string $directory;

    private PhpFilesCache $pool;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/derafu_cache_test_' . uniqid();
        $this->pool = new PhpFilesCache('test', $this->directory);
    }

    protected function tearDown(): void
    {
        $this->pool->clear();
    }

    public function testIsAPsr6CacheItemPool(): void
    {
        $this->assertInstanceOf(CacheItemPoolInterface::class, $this->pool);
    }

    public function testWritesExactlyToTheDirectoryGiven(): void
    {
        $item = $this->pool->getItem('example_key');
        $item->set(['name' => 'folios']);
        $this->pool->save($item);

        $this->assertDirectoryExists($this->directory);
        $this->assertNotEmpty(glob($this->directory . '/*'));
    }

    public function testSavesAndReadsBackAnItemAcrossASecondPoolOverTheSameDirectory(): void
    {
        $item = $this->pool->getItem('example_key');
        $item->set(['name' => 'folios', 'amount' => 3]);
        $this->pool->save($item);

        $reopened = new PhpFilesCache('test', $this->directory);
        $reopenedItem = $reopened->getItem('example_key');

        $this->assertTrue($reopenedItem->isHit());
        $this->assertSame(['name' => 'folios', 'amount' => 3], $reopenedItem->get());
    }

    public function testMissingItemIsNotAHit(): void
    {
        $this->assertFalse($this->pool->getItem('never_saved')->isHit());
    }
}
