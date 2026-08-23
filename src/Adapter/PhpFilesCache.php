<?php

declare(strict_types=1);

/**
 * Derafu: Cache - Consistent PSR-6/PSR-16 Cache Wiring Across Derafu Packages.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Cache\Adapter;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

/**
 * OPcache-friendly PSR-6 pool: entries are written as plain PHP files
 * returning an array, so once compiled, a warm OPcache serves reads
 * without touching disk again. Values must be `var_export()`-able — no
 * objects, only arrays/scalars.
 *
 * `$namespace` and `$directory` are both required, deliberately with no
 * default: a cache backed by the filesystem that nobody can point to
 * exactly is a cache nobody can clear with confidence. If you don't know
 * (or don't care) where it writes, that is itself a decision worth making
 * explicitly, not one this class should make for you.
 */
final class PhpFilesCache extends PhpFilesAdapter implements CacheItemPoolInterface
{
    public function __construct(
        string $namespace,
        string $directory,
        int $defaultLifetime = 3600,
    ) {
        parent::__construct($namespace, $defaultLifetime, $directory);
    }
}
