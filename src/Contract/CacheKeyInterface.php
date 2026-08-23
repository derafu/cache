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
 * Builds a single PSR-6/PSR-16-safe cache key from a prefix plus arbitrary
 * context.
 *
 * `CacheKey` covers the generic case (sanitize strings, hash anything else,
 * collapse if too long) with no knowledge of what `$parts` actually is. A
 * consumer that needs key logic driven by the *meaning* of what it's
 * caching — e.g. deriving the key from a domain object's id and version
 * instead of hashing the whole object — implements this interface directly
 * instead of subclassing `CacheKey`: composition over inheritance, since
 * `CacheKey`'s own sanitizing/hashing helpers are private and not meant to
 * be partially reused by a subclass.
 */
interface CacheKeyInterface
{
    /**
     * @param string $prefix
     * @param mixed ...$parts
     * @return string
     */
    public function build(string $prefix, mixed ...$parts): string;
}
