<?php

declare(strict_types=1);

/**
 * Derafu: Cache - Consistent PSR-6/PSR-16 Cache Wiring Across Derafu Packages.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Cache;

use Derafu\Translation\Exception\Core\TranslatableRuntimeException;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Inspects and clears a filesystem-backed cache directory by path alone —
 * without needing to reconstruct whichever `PhpFilesCache`/`FilesystemCache`
 * (namespace, TTL, backend) originally wrote there.
 *
 * This does not duplicate `Psr\Cache\CacheItemPoolInterface::clear()`
 * (already part of the PSR-6 contract, works fine when you have a live
 * pool instance in hand): it exists for exactly the case where you don't
 * have one — a `derafu:local-cache:clear /var/cache/backbone_dispatcher`
 * admin command that only knows a path, not which adapter type wrote it
 * (see `Console\ClearLocalCacheCommand`) — and for
 * `stats()`, which has no PSR-6/`symfony/cache` equivalent at all: neither
 * defines a way to ask a pool how many entries it holds or how much space
 * they use.
 */
final class CacheDirectory
{
    public function __construct(
        private readonly string $path,
    ) {
    }

    /**
     * Returns aggregate stats for every file under this directory.
     *
     * @return array{count: int, totalSize: int, oldest: int|null, newest: int|null}
     */
    public function stats(): array
    {
        $count = 0;
        $totalSize = 0;
        $oldest = null;
        $newest = null;

        foreach ($this->files() as $file) {
            try {
                clearstatcache(true, $file->getPathname());
                $size = $file->getSize();
                clearstatcache(true, $file->getPathname());
                $mtime = $file->getMTime();
                if ($size === false || $mtime === false) {
                    throw new TranslatableRuntimeException([
                        'Could not read metadata for cache file {file}.',
                        'file' => $file->getPathname(),
                    ]);
                }
            } catch (RuntimeException $exception) {
                $path = $file->getPathname();
                clearstatcache(true, $path);
                if (!file_exists($path)) {
                    continue;
                }

                throw $exception;
            }

            $count++;
            $totalSize += $size;
            $oldest = $oldest === null ? $mtime : min($oldest, $mtime);
            $newest = $newest === null ? $mtime : max($newest, $mtime);
        }

        return [
            'count' => $count,
            'totalSize' => $totalSize,
            'oldest' => $oldest,
            'newest' => $newest,
        ];
    }

    /**
     * Deletes the entire directory (and everything under it), regardless
     * of which adapter wrote its contents.
     *
     * @return int The number of files deleted.
     */
    public function clear(): int
    {
        $count = iterator_count($this->files());

        (new Filesystem())->remove($this->path);

        return $count;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function files(): iterable
    {
        if (!is_dir($this->path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                yield $file;
            }
        }
    }
}
