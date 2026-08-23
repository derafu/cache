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

use Derafu\Cache\Adapter\FilesystemCache;
use Derafu\Cache\Adapter\PhpFilesCache;
use Derafu\Cache\Enum\LocalCacheBackend;
use Derafu\Cache\LocalCacheFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[CoversClass(LocalCacheFactory::class)]
#[UsesClass(PhpFilesCache::class)]
#[UsesClass(FilesystemCache::class)]
class LocalCacheFactoryTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/derafu_cache_factory_test_' . uniqid();
    }

    public function testNoneBackendNeedsNoDirectoryAndNeverCaches(): void
    {
        $pool = LocalCacheFactory::pool(LocalCacheBackend::None, 'test');

        $this->assertInstanceOf(NullAdapter::class, $pool);

        $item = $pool->getItem('example_key');
        $item->set('computed');
        $pool->save($item);

        $this->assertFalse($pool->getItem('example_key')->isHit());
    }

    public function testMemoryBackendNeedsNoDirectory(): void
    {
        $pool = LocalCacheFactory::pool(LocalCacheBackend::Memory, 'test');

        $this->assertInstanceOf(CacheItemPoolInterface::class, $pool);
        $this->assertInstanceOf(ArrayAdapter::class, $pool);
    }

    public function testApcuBackendNeedsNoDirectory(): void
    {
        if (!ApcuAdapter::isSupported()) {
            $this->markTestSkipped('The apcu extension is not enabled.');
        }

        $pool = LocalCacheFactory::pool(LocalCacheBackend::Apcu, 'test');

        $this->assertInstanceOf(ApcuAdapter::class, $pool);
    }

    public function testFilesystemBackendBuildsAFilesystemCache(): void
    {
        $pool = LocalCacheFactory::pool(LocalCacheBackend::Filesystem, 'test', $this->directory);

        $this->assertInstanceOf(FilesystemCache::class, $pool);
    }

    public function testPhpFilesBackendBuildsAPhpFilesCache(): void
    {
        $pool = LocalCacheFactory::pool(LocalCacheBackend::PhpFiles, 'test', $this->directory);

        $this->assertInstanceOf(PhpFilesCache::class, $pool);
    }

    public function testFilesystemBackendThrowsWithoutADirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'A directory is required for the "filesystem" cache backend.'
        );

        LocalCacheFactory::pool(LocalCacheBackend::Filesystem, 'test');
    }

    public function testPhpFilesBackendThrowsWithoutADirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'A directory is required for the "php_files" cache backend.'
        );

        LocalCacheFactory::pool(LocalCacheBackend::PhpFiles, 'test');
    }

    public function testSimpleReturnsAPsr16CacheWrappingTheSamePool(): void
    {
        $cache = LocalCacheFactory::simple(LocalCacheBackend::Memory, 'test');

        $this->assertInstanceOf(CacheInterface::class, $cache);

        $cache->set('example_key', ['name' => 'folios']);

        $this->assertSame(['name' => 'folios'], $cache->get('example_key'));
    }

    public function testTaggedPoolInvalidatesEveryEntryUnderATagAtOnce(): void
    {
        $cache = LocalCacheFactory::taggedPool(LocalCacheBackend::Memory, 'test');

        $this->assertInstanceOf(TagAwareCacheInterface::class, $cache);

        $cache->get('example_key_a', function (ItemInterface $item) {
            $item->tag('example_tag');

            return 'a';
        });
        $cache->get('example_key_b', function (ItemInterface $item) {
            $item->tag('example_tag');

            return 'b';
        });

        $cache->invalidateTags(['example_tag']);

        $calls = 0;
        $cache->get('example_key_a', function (ItemInterface $item) use (&$calls) {
            $calls++;

            return 'a';
        });

        $this->assertSame(1, $calls);
    }

    public function testLayeredCombinesPoolsFastestFirst(): void
    {
        $pool = LocalCacheFactory::layered([new ArrayAdapter(), new ArrayAdapter()]);

        $this->assertInstanceOf(ChainAdapter::class, $pool);
        $this->assertInstanceOf(CacheItemPoolInterface::class, $pool);

        $item = $pool->getItem('example_key');
        $item->set('computed');
        $pool->save($item);

        $this->assertTrue($pool->getItem('example_key')->isHit());
    }
}
