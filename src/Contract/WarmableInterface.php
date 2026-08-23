<?php

declare(strict_types=1);

/**
 * Derafu: Cache - Consistent PSR-6/PSR-16 Cache Wiring Across Derafu Packages.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Cache\Contract;

/**
 * Something that can pre-populate its own cache ahead of real traffic —
 * e.g. reflecting every worker of a registry once, at deploy time, so the
 * first real request never pays that cost.
 *
 * Deliberately narrow: no arguments in, nothing out. Anything a warmable
 * needs (which cache, which data to compute) is a constructor concern for
 * the class that implements this, not something `CacheWarmer` should know
 * about or pass through.
 */
interface WarmableInterface
{
    public function warmup(): void;
}
