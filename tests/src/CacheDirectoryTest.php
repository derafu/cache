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

use Derafu\Cache\Adapter\PhpFilesCache;
use Derafu\Cache\CacheDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheDirectory::class)]
#[UsesClass(PhpFilesCache::class)]
class CacheDirectoryTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/derafu_cache_directory_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        (new CacheDirectory($this->path))->clear();
    }

    public function testStatsOnANonexistentDirectoryIsAllZeroes(): void
    {
        $stats = (new CacheDirectory($this->path))->stats();

        $this->assertSame(0, $stats['count']);
        $this->assertSame(0, $stats['totalSize']);
        $this->assertNull($stats['oldest']);
        $this->assertNull($stats['newest']);
    }

    public function testStatsCountsRealCacheFiles(): void
    {
        $pool = new PhpFilesCache('test', $this->path);
        $item = $pool->getItem('example_key');
        $item->set(['name' => 'folios']);
        $pool->save($item);

        $stats = (new CacheDirectory($this->path))->stats();

        $this->assertGreaterThan(0, $stats['count']);
        $this->assertGreaterThan(0, $stats['totalSize']);
        $this->assertNotNull($stats['oldest']);
        $this->assertNotNull($stats['newest']);
    }

    public function testClearOnANonexistentDirectoryDeletesNothing(): void
    {
        $this->assertSame(0, (new CacheDirectory($this->path))->clear());
    }

    public function testClearDeletesEveryFileAndTheDirectoryItself(): void
    {
        $pool = new PhpFilesCache('test', $this->path);
        $item = $pool->getItem('example_key');
        $item->set(['name' => 'folios']);
        $pool->save($item);

        $deleted = (new CacheDirectory($this->path))->clear();

        $this->assertGreaterThan(0, $deleted);
        $this->assertDirectoryDoesNotExist($this->path);
    }
}
