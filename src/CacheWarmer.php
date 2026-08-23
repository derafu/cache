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

use Derafu\Cache\Contract\WarmableInterface;
use Throwable;

/**
 * Runs a list of `WarmableInterface` in order, deliberately not the
 * Kernel-coupled thing Symfony calls a "cache warmer".
 *
 * `Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface` (in
 * `symfony/http-kernel`, not present here at all) warms the *framework's
 * own* caches (compiled container, routing) into a temp location and
 * atomically swaps it in, specifically because a half-written container
 * cache would take the whole application down — that machinery exists to
 * guarantee zero downtime for that one, high-stakes case.
 *
 * Warming an application-level PSR-6 cache doesn't carry that risk: if a
 * warmable fails or only gets partway through, the worst outcome is that
 * the next real read recomputes that one entry, not a broken app. So this
 * doesn't try to replicate the atomic swap or the Kernel boot-order
 * resolution — it runs each warmable, keeps going if one throws, and
 * reports what failed at the end instead of stopping at the first error.
 *
 * On purpose, there is no `Console\WarmupCommand` next to this the way
 * `ClearLocalCacheCommand`/`LocalCacheStatsCommand` sit next to
 * `CacheDirectory`. Those two only need a path — nothing app-specific.
 * A generic warmup command would need to *discover* which
 * `WarmableInterface` instances exist in a given application (and how to
 * construct each one, since almost none of them will have a no-argument
 * constructor — a real warmable needs whatever it's warming, e.g. an
 * `Inspector` and a registry to reflect against). That discovery is
 * exactly what a DI container is for, which is precisely what this
 * package chose not to depend on (see `LocalCacheFactory`'s docblock on
 * why `derafu/console` — Kernel + DI + YAML — was the wrong dependency
 * for two path-based commands). The intended shape: your own application
 * writes a small command that constructs the warmables it actually has
 * and calls `(new CacheWarmer())->warmup($warmables)` — a handful of
 * lines, but ones only your application can write.
 */
class CacheWarmer
{
    /**
     * @param iterable<WarmableInterface> $warmables
     * @return list<array{warmable: WarmableInterface, exception: Throwable}>
     *     Empty if every warmable succeeded.
     */
    public function warmup(iterable $warmables): array
    {
        $failures = [];

        foreach ($warmables as $warmable) {
            try {
                $warmable->warmup();
            } catch (Throwable $exception) {
                $failures[] = [
                    'warmable' => $warmable,
                    'exception' => $exception,
                ];
            }
        }

        return $failures;
    }
}
