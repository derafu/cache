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

/**
 * Builds a PSR-6/PSR-16-safe cache key from a prefix plus arbitrary
 * context — a class name, a set of filters, anything.
 *
 * PSR-6 forbids `{}()/\@:` in keys but does not sanitize anything for
 * you; building a safe, collision-resistant key from real context (a
 * class name with backslashes, an array of filters) is left entirely to
 * each caller. Two different, ad hoc answers to that already exist in the
 * derafu ecosystem — `CachedInspector::key()` in `backbone-dispatcher`
 * (`str_replace('\\', '.', $class)` plus `md5(serialize($filters))` for
 * everything else) and `AbstractContentRegistry` in `derafu-content`
 * (`md5(serialize([...]))` for the whole thing) — this centralizes it
 * into one rule instead of each package reinventing its own.
 *
 * A service, not a static utility, for the same reason as `Memoizer`:
 * every other collaborator in this ecosystem is instantiated and
 * injected, not called statically.
 */
class CacheKey
{
    /**
     * Memcached's real limit (250 bytes) — short enough to be a safe
     * ceiling for any backend, not just Memcached.
     */
    private const int DEFAULT_MAX_LENGTH = 250;

    public function __construct(
        private readonly int $maxLength = self::DEFAULT_MAX_LENGTH,
    ) {
    }

    /**
     * Builds a single safe key from `$prefix` and each of `$parts`, in
     * order.
     *
     * Each part is normalized on its own: a string gets its forbidden
     * characters replaced (never dropped — that would silently create
     * collisions between different inputs); any other scalar is used as
     * a plain string; anything else (an array, an object) is hashed,
     * since PSR-6 keys must be strings and there is no safe general way
     * to inline an arbitrary structure into one. If the assembled key
     * would exceed `$maxLength`, the whole thing collapses to
     * `$prefix` + a hash of the full key — long enough context (a deep
     * filter array, a long class name) never silently breaks a backend
     * with a real key-length limit.
     *
     * @param string $prefix
     * @param mixed ...$parts
     * @return string
     */
    public function build(string $prefix, mixed ...$parts): string
    {
        $segments = array_map(
            $this->normalize(...),
            array_filter($parts, fn ($part) => $part !== null && $part !== ''),
        );

        $key = implode('.', [$this->sanitize($prefix), ...$segments]);

        if (strlen($key) > $this->maxLength) {
            return $this->sanitize($prefix) . '.' . md5($key);
        }

        return $key;
    }

    /**
     * @param mixed $part
     * @return string
     */
    private function normalize(mixed $part): string
    {
        if (is_string($part)) {
            return $this->sanitize($part);
        }

        if (is_scalar($part)) {
            return (string) $part;
        }

        return md5(serialize($part));
    }

    /**
     * Replaces every character PSR-6 forbids in a key
     * (`{`, `}`, `(`, `)`, `/`, `\`, `@`, `:`) with `.` — chosen because
     * it is already the separator this class joins parts with, so a
     * sanitized class name (`App.Foo.Bar` from `App\Foo\Bar`) reads the
     * same way the rest of the key does.
     *
     * @param string $value
     * @return string
     */
    private function sanitize(string $value): string
    {
        return str_replace(
            ['{', '}', '(', ')', '/', '\\', '@', ':'],
            '.',
            $value,
        );
    }
}
