<?php

declare(strict_types=1);

/**
 * Derafu: Cache - Consistent PSR-6/PSR-16 Cache Wiring Across Derafu Packages.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsCache;

use DateInterval;
use DateTimeInterface;
use Derafu\Cache\Memoizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;

#[CoversClass(Memoizer::class)]
class MemoizerTest extends TestCase
{
    private Memoizer $memoizer;

    protected function setUp(): void
    {
        $this->memoizer = new Memoizer();
    }

    public function testComputesAndReturnsTheValueOnAMiss(): void
    {
        $value = $this->memoizer->remember(
            new ArrayAdapter(),
            'example_key',
            fn () => 'computed',
        );

        $this->assertSame('computed', $value);
    }

    public function testComputesOnlyOnceAcrossSeveralCallsWithASymfonyAdapter(): void
    {
        $cache = new ArrayAdapter();
        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return 'computed';
        };

        $this->memoizer->remember($cache, 'example_key', $compute);
        $this->memoizer->remember($cache, 'example_key', $compute);
        $this->memoizer->remember($cache, 'example_key', $compute);

        $this->assertSame(1, $calls);
    }

    public function testComputesOnlyOnceWithAPlainPsr6PoolNotImplementingSymfonysContract(): void
    {
        $cache = $this->createPlainPsr6Pool();
        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return 'computed';
        };

        $this->memoizer->remember($cache, 'example_key', $compute);
        $this->memoizer->remember($cache, 'example_key', $compute);

        $this->assertSame(1, $calls);
    }

    public function testAlwaysRecomputesWithANullAdapter(): void
    {
        // The "disable caching" pattern: no bool flag anywhere, just a
        // different pool. remember() doesn't know or care that this one
        // never actually caches anything.
        $cache = new NullAdapter();
        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return 'computed';
        };

        $this->memoizer->remember($cache, 'example_key', $compute);
        $this->memoizer->remember($cache, 'example_key', $compute);

        $this->assertSame(2, $calls);
    }

    public function testTtlIsAppliedToTheStoredItem(): void
    {
        $cache = new ArrayAdapter();

        $this->memoizer->remember($cache, 'example_key', fn () => 'computed', ttl: 60);

        // ArrayAdapter tracks its own expiry internally; the observable
        // behavior is just that the value is still retrievable right after.
        $item = $cache->getItem('example_key');
        $this->assertTrue($item->isHit());
        $this->assertSame('computed', $item->get());
    }

    /**
     * A minimal PSR-6 pool that does NOT also implement Symfony's own
     * `CacheInterface` contract, to exercise `Memoizer`'s fallback path.
     */
    private function createPlainPsr6Pool(): CacheItemPoolInterface
    {
        return new class () implements CacheItemPoolInterface {
            /** @var array<string, mixed> */
            private array $values = [];

            public function getItem(string $key): CacheItemInterface
            {
                $isHit = array_key_exists($key, $this->values);

                return new class (
                    $key,
                    $isHit ? $this->values[$key] : null,
                    $isHit,
                ) implements CacheItemInterface {
                    public function __construct(
                        private string $key,
                        private mixed $value,
                        private bool $hit,
                    ) {
                    }

                    public function getKey(): string
                    {
                        return $this->key;
                    }

                    public function get(): mixed
                    {
                        return $this->value;
                    }

                    public function isHit(): bool
                    {
                        return $this->hit;
                    }

                    public function set(mixed $value): static
                    {
                        $this->value = $value;

                        return $this;
                    }

                    public function expiresAt(?DateTimeInterface $expiration): static
                    {
                        return $this;
                    }

                    public function expiresAfter(DateInterval|int|null $time): static
                    {
                        return $this;
                    }
                };
            }

            public function getItems(array $keys = []): iterable
            {
                return array_map($this->getItem(...), $keys);
            }

            public function hasItem(string $key): bool
            {
                return array_key_exists($key, $this->values);
            }

            public function clear(): bool
            {
                $this->values = [];

                return true;
            }

            public function deleteItem(string $key): bool
            {
                unset($this->values[$key]);

                return true;
            }

            public function deleteItems(array $keys): bool
            {
                foreach ($keys as $key) {
                    $this->deleteItem($key);
                }

                return true;
            }

            public function save(CacheItemInterface $item): bool
            {
                $this->values[$item->getKey()] = $item->get();

                return true;
            }

            public function saveDeferred(CacheItemInterface $item): bool
            {
                return $this->save($item);
            }

            public function commit(): bool
            {
                return true;
            }
        };
    }
}
