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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\CacheInterface as SymfonyCacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Computes a value once and caches it — the "is it there? no? compute it,
 * store it, return it" pattern every hand-written PSR-6 caching decorator
 * ends up rewriting.
 *
 * A plain `getItem()`/`isHit()`/`save()` dance (the naive version) has no
 * protection against a cache stampede: under concurrent access, every
 * process that hits a cold cache computes and saves independently. Every
 * `symfony/cache` adapter also implements Symfony's own
 * `Symfony\Contracts\Cache\CacheInterface::get()`, which does the same
 * thing but with real locking — only one process computes, the rest wait
 * for it and read the result. `remember()` uses that path whenever it's
 * available, and falls back to the naive dance only for a PSR-6
 * implementation that isn't a Symfony adapter (no locking possible without
 * Symfony's own contract).
 *
 * A service, not a static utility: every other collaborator in this
 * ecosystem is instantiated and injected, not called statically — this
 * follows the same convention instead of being the one exception.
 */
class Memoizer
{
    /**
     * Returns the cached value for `$key`, computing and storing it first
     * on a miss.
     *
     * `$ttl` means exactly what it means for `Psr\Cache\CacheItemInterface::expiresAfter()`:
     * `null` leaves the pool's own default lifetime in effect, `0` means
     * the entry never expires, a positive integer is seconds until
     * expiration. Nothing here redefines that — a decorator that also
     * needs an "don't even try to cache" switch should model it as its
     * own, separately-named parameter (e.g. a plain `bool $enabled`), never
     * by repurposing a TTL value Symfony/PSR-6 already gives a real
     * meaning to.
     *
     * @param CacheItemPoolInterface $cache
     * @param string $key
     * @param callable $compute
     * @param int|null $ttl
     * @return mixed
     */
    public function remember(
        CacheItemPoolInterface $cache,
        string $key,
        callable $compute,
        ?int $ttl = null,
    ): mixed {
        if ($cache instanceof SymfonyCacheInterface) {
            return $cache->get($key, function (ItemInterface $item) use ($compute, $ttl) {
                if ($ttl !== null) {
                    $item->expiresAfter($ttl);
                }

                return $compute();
            });
        }

        $item = $cache->getItem($key);

        if ($item->isHit()) {
            return $item->get();
        }

        $value = $compute();

        $item->set($value);
        if ($ttl !== null) {
            $item->expiresAfter($ttl);
        }
        $cache->save($item);

        return $value;
    }
}
