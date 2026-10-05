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

use Derafu\Cache\Adapter\FilesystemCache;
use Derafu\Cache\Adapter\PhpFilesCache;
use Derafu\Cache\Enum\LocalCacheBackend;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Builds a PSR-6 pool (or a PSR-16 cache wrapping one) from a
 * `LocalCacheBackend` chosen at runtime — e.g. from application
 * configuration, the way a per-feature "cache: memory|filesystem" setting
 * would.
 *
 * Deliberately scoped to *local* backends only (see `LocalCacheBackend`'s
 * own docblock) — this is not a universal factory for every backend
 * `symfony/cache` supports. A connection-based backend (Redis, Memcached,
 * ...) has a fundamentally different construction shape (a connection or
 * DSN, not a namespace/directory pair) and none of the "silent default"
 * problems this factory exists to close off in the first place — building
 * one directly from `symfony/cache` is not a workaround, it's simply
 * outside what this class is for.
 *
 * For the common case of already knowing exactly which backend you want,
 * constructing `PhpFilesCache`/`FilesystemCache` directly is simpler than
 * going through this factory — it exists for the case where the backend
 * itself is a runtime choice, not a compile-time one.
 */
final class LocalCacheFactory
{
    /**
     * Builds a PSR-6 pool for the given backend.
     *
     * Typed to Symfony's own `AdapterInterface` (which extends PSR-6's
     * `CacheItemPoolInterface`), not the bare PSR-6 interface: every value
     * this can return actually is a Symfony adapter, and `taggedPool()`
     * needs that more specific type to wrap it in `TagAwareAdapter`
     * (which, unlike `ChainAdapter`, requires it — no auto-wrapping
     * fallback for a plain PSR-6 pool).
     *
     * `$directory` is required for `Filesystem` and `PhpFiles` (both write
     * to disk) and ignored for `None`/`Memory`/`Apcu` (none of which do)
     * — passing it for those three is harmless, omitting it for the other
     * two is an error, not a silently chosen default.
     *
     * `LocalCacheBackend::None` is how "no caching" is expressed: not a
     * runtime toggle a consumer has to check, but a pool like any other —
     * a decorator that always receives a `CacheItemPoolInterface` needs no
     * special case for "caching is off", it just does nothing useful when
     * this happens to be the pool it got. If a consumer never needs to
     * choose its backend at runtime, not applying the caching decorator
     * at all is simpler still — this exists for when the choice does come
     * from configuration, alongside `Memory`/`Apcu`/`Filesystem`/`PhpFiles`.
     *
     * @param LocalCacheBackend $backend
     * @param string $namespace
     * @param string|null $directory
     * @param int $defaultLifetime
     * @return AdapterInterface
     */
    public static function pool(
        LocalCacheBackend $backend,
        string $namespace,
        ?string $directory = null,
        int $defaultLifetime = 3600,
    ): AdapterInterface {
        $needsDirectory = !in_array(
            $backend,
            [LocalCacheBackend::None, LocalCacheBackend::Memory, LocalCacheBackend::Apcu],
            true,
        );

        if ($needsDirectory && $directory === null) {
            throw new InvalidArgumentException([
                'A directory is required for the "{backend}" cache backend.',
                'backend' => $backend->value,
            ]);
        }

        return match ($backend) {
            LocalCacheBackend::None => new NullAdapter(),
            LocalCacheBackend::Memory => new ArrayAdapter($defaultLifetime),
            LocalCacheBackend::Apcu => new ApcuAdapter($namespace, $defaultLifetime),
            LocalCacheBackend::Filesystem => new FilesystemCache($namespace, $directory, $defaultLifetime),
            LocalCacheBackend::PhpFiles => new PhpFilesCache($namespace, $directory, $defaultLifetime),
        };
    }

    /**
     * Builds a PSR-16 `CacheInterface` for the given backend — the same
     * pool `pool()` would build, wrapped in Symfony's `Psr16Cache` bridge.
     *
     * @param LocalCacheBackend $backend
     * @param string $namespace
     * @param string|null $directory
     * @param int $defaultLifetime
     * @return CacheInterface
     */
    public static function simple(
        LocalCacheBackend $backend,
        string $namespace,
        ?string $directory = null,
        int $defaultLifetime = 3600,
    ): CacheInterface {
        return new Psr16Cache(self::pool($backend, $namespace, $directory, $defaultLifetime));
    }

    /**
     * Builds a tag-aware pool for the given backend: the same pool `pool()`
     * would build, wrapped in Symfony's `TagAwareAdapter` — `$item->tag([...])`
     * on save, `invalidateTags([...])` to drop every entry under a tag at
     * once, instead of tracking individual keys yourself.
     *
     * @param LocalCacheBackend $backend
     * @param string $namespace
     * @param string|null $directory
     * @param int $defaultLifetime
     * @return TagAwareCacheInterface
     */
    public static function taggedPool(
        LocalCacheBackend $backend,
        string $namespace,
        ?string $directory = null,
        int $defaultLifetime = 3600,
    ): TagAwareCacheInterface {
        return new TagAwareAdapter(self::pool($backend, $namespace, $directory, $defaultLifetime));
    }

    /**
     * Combines several pools into one, checked fastest-first: a read that
     * hits a slower tier is backfilled into every faster one, so the next
     * read of the same key is fast again. Typically a request-local
     * `ArrayAdapter` in front of a persistent pool from `pool()`, so a
     * value read many times in one process is only fetched from disk once.
     *
     * Accepts any PSR-6 pool, not just ones `pool()` built — a `RedisAdapter`
     * you constructed yourself works here too, `ChainAdapter` doesn't care.
     *
     * @param CacheItemPoolInterface[] $pools Ordered fastest to slowest.
     * @param int $defaultLifetime
     * @return CacheItemPoolInterface
     */
    public static function layered(
        array $pools,
        int $defaultLifetime = 0,
    ): CacheItemPoolInterface {
        return new ChainAdapter($pools, $defaultLifetime);
    }
}
