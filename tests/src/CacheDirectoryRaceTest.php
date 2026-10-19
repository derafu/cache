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

use Derafu\Cache\CacheDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(CacheDirectory::class)]
final class CacheDirectoryRaceTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/derafu_cache_race_' . uniqid();
        mkdir($this->path);
        CacheDirectoryRaceStream::$directory = $this->path;
        CacheDirectoryRaceStream::$queries = [];
        CacheDirectoryRaceStream::$failed = false;
        CacheDirectoryRaceStream::$removeOnFailure = true;
        $this->assertTrue(stream_wrapper_register('cachetest', CacheDirectoryRaceStream::class));
    }

    protected function tearDown(): void
    {
        stream_wrapper_unregister('cachetest');
        clearstatcache();
        (new Filesystem())->remove($this->path);
        CacheDirectoryRaceStream::$directory = '';
        CacheDirectoryRaceStream::$queries = [];
    }

    /** @return array<string, array{int, bool}> */
    public static function metadataQueries(): array
    {
        // The first query belongs to directory iteration, the second to
        // getSize(), and the third to getMTime().
        return [
            'size query returning false' => [2, false],
            'size query throwing' => [2, true],
            'modification time query returning false' => [3, false],
            'modification time query throwing' => [3, true],
        ];
    }

    #[DataProvider('metadataQueries')]
    public function testAFileDeletedDuringMetadataLookupIsExcludedFromAllStatistics(int $failAt, bool $throwOnFailure): void
    {
        file_put_contents($this->path . '/first', 'ab');
        file_put_contents($this->path . '/changing', str_repeat('x', 100));
        file_put_contents($this->path . '/last', 'abcd');
        touch($this->path . '/first', 1000);
        touch($this->path . '/changing', $failAt === 2 ? 1 : 3000);
        touch($this->path . '/last', 2000);
        CacheDirectoryRaceStream::$failAt = $failAt;
        CacheDirectoryRaceStream::$throwOnFailure = $throwOnFailure;

        $stats = (new CacheDirectory('cachetest://root'))->stats();

        $this->assertTrue(CacheDirectoryRaceStream::$failed);
        $this->assertFileDoesNotExist($this->path . '/changing');
        $this->assertSame(['count' => 2, 'totalSize' => 6, 'oldest' => 1000, 'newest' => 2000], $stats);
        $this->assertSame('ab', file_get_contents($this->path . '/first'));
        $this->assertSame('abcd', file_get_contents($this->path . '/last'));
    }

    #[DataProvider('metadataQueries')]
    public function testDeletingTheOnlyFileLeavesEmptyStatistics(int $failAt, bool $throwOnFailure): void
    {
        file_put_contents($this->path . '/changing', 'removed');
        CacheDirectoryRaceStream::$failAt = $failAt;
        CacheDirectoryRaceStream::$throwOnFailure = $throwOnFailure;

        $stats = (new CacheDirectory('cachetest://root'))->stats();

        $this->assertTrue(CacheDirectoryRaceStream::$failed);
        $this->assertSame(['count' => 0, 'totalSize' => 0, 'oldest' => null, 'newest' => null], $stats);
        $this->assertFileDoesNotExist($this->path . '/changing');
    }

    #[DataProvider('metadataQueries')]
    public function testAMetadataFailureForAnExistingFileIsNotHidden(int $failAt, bool $throwOnFailure): void
    {
        file_put_contents($this->path . '/changing', 'still present');
        CacheDirectoryRaceStream::$failAt = $failAt;
        CacheDirectoryRaceStream::$throwOnFailure = $throwOnFailure;
        CacheDirectoryRaceStream::$removeOnFailure = false;

        try {
            (new CacheDirectory('cachetest://root'))->stats();
            $this->fail('A metadata error on an existing file must be propagated.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('changing', $exception->getMessage());
            $this->assertTrue(CacheDirectoryRaceStream::$failed);
            $this->assertSame('still present', file_get_contents($this->path . '/changing'));
        }
    }

    public function testRealNestedFilesContributeExactSizesAndDates(): void
    {
        mkdir($this->path . '/nested');
        file_put_contents($this->path . '/first', 'ab');
        file_put_contents($this->path . '/nested/last', 'abcd');
        touch($this->path . '/first', 1000);
        touch($this->path . '/nested/last', 2000);

        $this->assertSame(
            ['count' => 2, 'totalSize' => 6, 'oldest' => 1000, 'newest' => 2000],
            (new CacheDirectory($this->path))->stats()
        );
    }
}

/**
 * Forwards directory access and stat queries to real temporary files. A
 * selected query deletes one file before asking the filesystem for its
 * metadata, so the disappearance is reproducible without process timing.
 */
final class CacheDirectoryRaceStream
{
    public mixed $context;

    public static string $directory = '';

    public static int $failAt = 2;

    public static bool $removeOnFailure = true;

    public static bool $throwOnFailure = false;

    public static bool $failed = false;

    /** @var array<string, int> */
    public static array $queries = [];

    /** @var resource|false */
    private mixed $handle = false;

    /** @return array<int|string, int>|false */
    public function url_stat(string $path, int $flags): array|false
    {
        $realPath = self::$directory . substr($path, strlen('cachetest://root'));
        if (basename($realPath) === 'changing') {
            self::$queries[$path] = (self::$queries[$path] ?? 0) + 1;
            if (self::$queries[$path] === self::$failAt) {
                self::$failed = true;
                if (self::$removeOnFailure) {
                    unlink($realPath);
                }
                if (self::$throwOnFailure) {
                    throw new RuntimeException('Metadata lookup failed for ' . $path);
                }

                return false;
            }
        }
        clearstatcache(true, $realPath);

        return file_exists($realPath) ? stat($realPath) : false;
    }

    public function dir_opendir(string $path, int $options): bool
    {
        $this->handle = opendir(self::$directory . substr($path, strlen('cachetest://root')));

        return $this->handle !== false;
    }

    public function dir_readdir(): string|false
    {
        return $this->handle === false ? false : readdir($this->handle);
    }

    public function dir_rewinddir(): bool
    {
        if ($this->handle === false) {
            return false;
        }
        rewinddir($this->handle);

        return true;
    }

    public function dir_closedir(): bool
    {
        if ($this->handle !== false) {
            closedir($this->handle);
            $this->handle = false;
        }

        return true;
    }
}
