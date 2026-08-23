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
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * PSR-6 pool backed by serialized files on disk: supports arbitrary
 * serializable values, including objects — unlike `PhpFilesCache`, at the
 * cost of no OPcache benefit.
 *
 * `$namespace` and `$directory` are both required, deliberately with no
 * default — see `PhpFilesCache`'s docblock for why.
 */
final class FilesystemCache extends FilesystemAdapter implements CacheItemPoolInterface
{
    public function __construct(
        string $namespace,
        string $directory,
        int $defaultLifetime = 3600,
    ) {
        parent::__construct($namespace, $defaultLifetime, $directory);
    }
}
