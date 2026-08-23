<?php

declare(strict_types=1);

/**
 * Derafu: Cache - Consistent PSR-6/PSR-16 Cache Wiring Across Derafu Packages.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Cache\Enum;

/**
 * The "local" cache backends `LocalCacheFactory` knows how to build: none
 * of these need a connection, credentials, or a network round trip — they
 * live in this process, in shared memory on this server, or on this
 * filesystem. Named "local" deliberately, not just "cache backend": this
 * is not every backend `symfony/cache` supports (Redis, Memcached,
 * Couchbase, PDO, ... all need a connection, a fundamentally different
 * construction shape), it's specifically the family that shares one.
 * Reaching for one of those means using `symfony/cache` directly — that's
 * not a gap in this enum, it's out of scope for it on purpose.
 */
enum LocalCacheBackend: string
{
    /**
     * Never caches anything (`Symfony\Component\Cache\Adapter\NullAdapter`):
     * every read is a miss, every write is a no-op. Disabling caching is a
     * choice of *which pool gets built*, not a runtime flag a consumer
     * needs to check — a decorator that always receives a
     * `CacheItemPoolInterface` needs no special case for "caching is off",
     * it just does nothing useful when this is the pool it got.
     */
    case None = 'none';

    /**
     * In-memory only (`Symfony\Component\Cache\Adapter\ArrayAdapter`): dies
     * with the process, needs no directory.
     */
    case Memory = 'memory';

    /**
     * Shared memory on this server
     * (`Symfony\Component\Cache\Adapter\ApcuAdapter`): survives across
     * requests (unlike `Memory`), needs no directory either — but requires
     * the `apcu` PHP extension to be installed and enabled, or building it
     * throws.
     */
    case Apcu = 'apcu';

    /**
     * Serialized entries on disk
     * (`Symfony\Component\Cache\Adapter\FilesystemAdapter`): supports
     * arbitrary values (objects included), no OPcache benefit.
     */
    case Filesystem = 'filesystem';

    /**
     * Plain PHP array files on disk
     * (`Symfony\Component\Cache\Adapter\PhpFilesAdapter`): values must be
     * `var_export()`-able (arrays/scalars, no objects), OPcache-friendly —
     * once compiled, a warm OPcache serves reads without touching disk.
     */
    case PhpFiles = 'php_files';
}
